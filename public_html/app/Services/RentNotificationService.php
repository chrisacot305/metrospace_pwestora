<?php
/**
 * app/Services/RentNotificationService.php (was includes/payment_sms_helper.php) — Automated computation & SMS dispatch
 * for Pwestora rent receipts, pre-due reminders, and overdue grace notices.
 * ---------------------------------------------------------------------------
 * Constraints:
 *  - Never references unit numbers (uses "you" / "your monthly rent").
 *  - Calculates remaining monthly rent and remaining restructuring plan balances.
 *  - Applies ₱500 flat late fee upon post-grace overdue.
 *  - Uses PhilSMS Gateway via app/Services/Sms.php.
 */

require_once __DIR__ . '/Sms.php';
require_once __DIR__ . '/RentBillingService.php';

use App\Services\RentBillingService;

/**
 * Ensures the dedup tracking table for SMS reminders exists.
 */
function ensure_sms_reminder_logs_table(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `sms_reminder_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `tenant_id` INT NOT NULL,
            `lessee_id` INT DEFAULT NULL,
            `billing_month` VARCHAR(7) NOT NULL,
            `reminder_type` ENUM('3_day_advance', 'overdue_grace', 'restructure_due') NOT NULL,
            `amount_due` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `sent_to` VARCHAR(30) NOT NULL,
            `sms_message` TEXT NOT NULL,
            `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_reminder` (`tenant_id`, `billing_month`, `reminder_type`),
            INDEX `idx_tenant_month` (`tenant_id`, `billing_month`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );
}

/**
 * Synchronizes a single in-app chat reminder bubble between lessor and tenant.
 * Updates the existing bubble in-place as the countdown progresses (3 days -> 2 days -> 1 day -> Today -> Grace -> Overdue -> Settled),
 * so it never clutters or duplicates messages in the chat.
 */
function sync_chat_rent_reminder(PDO $pdo, int $tenantId, int $lessorId, int $diff, float $monthlyRent, float $rentRemaining, string $currentBillingMonth, array $billing = []): void {
    if ($monthlyRent <= 0 || $tenantId <= 0 || $lessorId <= 0) return;

    try {
        // Ensure message thread exists
        $threadStmt = $pdo->prepare('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?');
        $threadStmt->execute([$lessorId, $tenantId]);
        $threadId = $threadStmt->fetchColumn();
        if (!$threadId) {
            $pdo->prepare('INSERT INTO message_threads (lessor_id, tenant_id) VALUES (?, ?)')->execute([$lessorId, $tenantId]);
            $threadId = (int) $pdo->lastInsertId();
        }

        $formattedRent   = '₱' . number_format($monthlyRent, 2);
        $outstandingRent = (float) ($billing['outstanding_rent_total'] ?? $rentRemaining);
        $penaltyAmount   = (float) ($billing['penalty_amount'] ?? 0);
        $totalDue        = (float) ($billing['total_due'] ?? ($rentRemaining + $penaltyAmount));
        $strikeLevel     = (int) ($billing['strike_level'] ?? 0);
        $monthsLabel     = trim((string) ($billing['outstanding_months_label'] ?? $currentBillingMonth));
        $isEviction      = !empty($billing['is_eviction']);

        if ($rentRemaining <= 0.009 && $outstandingRent <= 0.009 && $penaltyAmount <= 0.009) {
            $msgBody = "✅ RENT FULLY SETTLED\n\nYour monthly rent for $currentBillingMonth has been received and verified. Your account is in good standing with zero arrears. Thank you!";
        } elseif (!empty($billing['is_overdue']) || $diff < -5) {
            $fmtTotal   = '₱' . number_format($totalDue, 2);
            $fmtPenalty = '₱' . number_format($penaltyAmount, 2);
            $strikeNote = $isEviction
                ? "Strike 3 Eviction Lock is active (includes {$fmtPenalty} penalty). Please settle your full balance in the Rent Payment page to lift the account lock."
                : ($strikeLevel >= 2
                    ? "Strike {$strikeLevel} is active (includes {$fmtPenalty} fine)."
                    : "Strike {$strikeLevel} overdue warning recorded (₱0 fine; Strike 2 adds ₱500.00, Strike 3 adds ₱1,500.00 + Eviction Lock).");
            $periodNote = $monthsLabel !== '' ? " ({$monthsLabel})" : '';
            $msgBody = "🚨 OFFICIAL OVERDUE & INFRACTION NOTICE\n\nYour 5-day grace period has ended and your balance{$periodNote} remains unpaid. Total Due: {$fmtTotal}. {$strikeNote}";
        } elseif (!empty($billing['is_grace_period']) || ($diff >= -5 && $diff <= -1)) {
            $daysLeft = (int) ($billing['grace_days_remaining'] ?? max(1, min(5, 6 + $diff)));
            $fmtRem   = '₱' . number_format(max(0.0, $rentRemaining), 2);
            if ($diff === -5 || $daysLeft === 1) {
                $msgBody = "⚠️ FINAL DAY OF GRACE PERIOD (1d Grace)\n\nThis is the last day of your 5-day grace period for $currentBillingMonth (Remaining: $fmtRem). Available Security Deposit balance is auto-applied at grace expiry. Any unpaid balance after today carries over into next month with a lease Strike recorded (Strike 1: ₱0, Strike 2: ₱500, Strike 3: ₱1,500 + Eviction Lock).";
            } else {
                $msgBody = "⏳ GRACE PERIOD ACTIVE ({$daysLeft}d Grace)\n\nYour monthly rent for $currentBillingMonth is past due ($fmtRem remaining). You have $daysLeft day" . ($daysLeft == 1 ? '' : 's') . " of your 5-day grace period remaining before carryover and Strike escalation.";
            }
        } elseif ($diff > 3) {
            return; // Outside of reminder threshold
        } elseif ($diff == 3) {
            $msgBody = "📅 OFFICIAL RENT NOTICE (Due in 3 Days)\n\nFriendly reminder: Your monthly rent of $formattedRent is due in 3 days. Please prepare your payment to maintain good standing.";
        } elseif ($diff == 2) {
            $msgBody = "📅 OFFICIAL RENT NOTICE (Due in 2 Days)\n\nFriendly reminder: Your monthly rent of $formattedRent is due in 2 days. Please prepare your payment.";
        } elseif ($diff == 1) {
            $msgBody = "📅 OFFICIAL RENT NOTICE (Due Tomorrow)\n\nFriendly reminder: Your monthly rent of $formattedRent is due tomorrow. Please submit your payment via the Pwestora app.";
        } elseif ($diff == 0) {
            $msgBody = "📅 OFFICIAL RENT NOTICE (Due Today)\n\nYour monthly rent of $formattedRent is officially due today. Please settle your payment to avoid entering the 5-day grace period.";
        } else {
            return;
        }

        // Look for the existing reminder bubble in this thread
        $checkMsg = $pdo->prepare(
            "SELECT id, body FROM messages 
             WHERE thread_id = ? AND notice_type = 'Rent Due Reminder'
             ORDER BY id DESC LIMIT 1"
        );
        $checkMsg->execute([$threadId]);
        $existing = $checkMsg->fetch();

        if ($existing) {
            // Update the exact same message bubble in-place if content changed
            if ($existing['body'] !== $msgBody) {
                $updateMsg = $pdo->prepare("UPDATE messages SET body = ?, sent_at = NOW() WHERE id = ?");
                $updateMsg->execute([$msgBody, $existing['id']]);
            }
        } else {
            // Insert the initial reminder message
            $ins = $pdo->prepare("INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, 'lessor', 'Rent Due Reminder', ?)");
            $ins->execute([$threadId, $msgBody]);
        }
    } catch (Throwable $e) {
        error_log('sync_chat_rent_reminder error: ' . $e->getMessage());
    }
}

/**
 * Dispatches a dynamic SMS payment receipt with automated remaining balance computation.
 * Called when a lessor approves an online payment OR logs an offline payment (cash/check).
 *
 * @param PDO $pdo
 * @param int $paymentId
 * @return array{ok: bool, message?: string, error?: string}
 */
function send_payment_receipt_sms(PDO $pdo, int $paymentId): array {
    try {
        $stmt = $pdo->prepare(
            'SELECT pay.*, t.tenant_name, t.lessee_id, t.created_at AS lease_start,
                    a.rent AS monthly_rent,
                    u.phone AS lessee_phone, u.full_name AS lessee_full_name
             FROM payments pay
             JOIN tenants t ON t.id = pay.tenant_id
             LEFT JOIN applications a ON a.id = t.application_id
             LEFT JOIN users u ON u.id = t.lessee_id
             WHERE pay.id = ?'
        );
        $stmt->execute([$paymentId]);
        $pRow = $stmt->fetch();

        if (!$pRow) {
            return ['ok' => false, 'error' => 'Payment record not found.'];
        }

        $phone = $pRow['lessee_phone'] ?? '';
        if (empty($phone)) {
            return ['ok' => false, 'error' => 'Tenant has no registered mobile number.'];
        }

        $amountPaid = (float) $pRow['amount'];
        $note = (string) ($pRow['note'] ?? '');

        // Extract reference number if present
        $refNo = '';
        if (preg_match('/Ref:\s*([A-Za-z0-9\-]+)/i', $note, $refMatch)) {
            $refNo = trim($refMatch[1]);
        }
        $refStr = $refNo ? " Ref #$refNo." : "";

        $isRestructuring = stripos($note, 'Restructuring') !== false && stripos($note, 'Both') === false;
        $isOverdue       = stripos($note, 'Overdue') !== false || stripos($note, 'Full Balance') !== false;
        $isDepositTopUp  = stripos($note, 'Security Deposit Top-Up') !== false
            || (stripos($note, 'security_deposit') !== false
                && stripos($note, '[AUTO-DEPOSIT]') === false
                && ($pRow['method'] ?? '') !== 'security_deposit');

        // 1. Compute authoritative remaining balance via RentBillingService (read-only)
        $billing       = (new RentBillingService($pdo))->computeForTenantId((int) $pRow['tenant_id'], false) ?? [];
        $monthlyRent   = (float) ($billing['base_rent'] ?? ($pRow['monthly_rent'] ?? 0));
        $rentRemaining = (float) ($billing['active_cycle_remaining'] ?? 0);
        $totalDueAfter = (float) ($billing['total_due'] ?? $rentRemaining);

        // 2. Calculate Restructuring Plan Remaining if active
        $planStmt = $pdo->prepare(
            "SELECT * FROM installment_requests WHERE tenant_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1"
        );
        $planStmt->execute([$pRow['tenant_id']]);
        $activePlan = $planStmt->fetch();

        $planRemaining = 0.0;
        if ($activePlan) {
            $planTotal = (float) $activePlan['amount'];
            $planPaidStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM payments 
                 WHERE tenant_id = ? AND (note LIKE '%[VERIFIED]%' OR id = ? OR logged_by IS NOT NULL)
                   AND note LIKE '%Restructuring%'
                   AND (note IS NULL OR note NOT LIKE '[DECLINED%')"
            );
            $planPaidStmt->execute([$pRow['tenant_id'], $paymentId]);
            $totalPlanPaid = (float) $planPaidStmt->fetchColumn();
            $planRemaining = max(0.0, $planTotal - $totalPlanPaid);
        }

        // 3. Formulate dynamic SMS receipt using personal "you" (no unit numbers)
        $formattedPaid = '₱' . number_format($amountPaid, 2);
        $smsBody = '';

        if ($isDepositTopUp) {
            $depStmt = $pdo->prepare('SELECT COALESCE(security_deposit, 0) FROM tenants WHERE id = ?');
            $depStmt->execute([$pRow['tenant_id']]);
            $currentDeposit = (float) $depStmt->fetchColumn();
            $formattedDep = '₱' . number_format($currentDeposit, 2);
            $smsBody = "[Pwestora] Security Deposit top-up of $formattedPaid confirmed!$refStr Your locked deposit balance is now $formattedDep. Thank you!";
        } elseif ($isRestructuring) {
            $formattedPlanRemaining = '₱' . number_format($planRemaining, 2);
            if ($totalDueAfter > 0.009 && $monthlyRent > 0) {
                $formattedRentRemaining = '₱' . number_format($totalDueAfter, 2);
                $smsBody = "[Pwestora] Restructuring installment of $formattedPaid received!$refStr Remaining plan balance: $formattedPlanRemaining. Notice: You still have an unpaid balance of $formattedRentRemaining.";
            } else {
                $smsBody = "[Pwestora] Restructuring installment of $formattedPaid received!$refStr Remaining plan balance: $formattedPlanRemaining. Your rent is also settled. Thank you!";
            }
        } elseif ($isOverdue) {
            if ($totalDueAfter <= 0.009) {
                $smsBody = "[Pwestora] Overdue balance payment of $formattedPaid received!$refStr Your arrears and penalties are now cleared and your account is in good standing. Thank you!";
            } else {
                $formattedRemDue = '₱' . number_format($totalDueAfter, 2);
                $smsBody = "[Pwestora] Payment of $formattedPaid received!$refStr Your remaining balance (rent/penalties) is $formattedRemDue. Please settle the balance in Pwestora.";
            }
        } else {
            // Regular monthly rent
            if ($totalDueAfter <= 0.009) {
                $smsBody = "[Pwestora] Payment received! $formattedPaid for your monthly rent.$refStr Your current rent balance is fully settled (Remaining balance: ₱0.00). Thank you!";
            } else {
                $formattedRentRemaining = '₱' . number_format($totalDueAfter, 2);
                $smsBody = "[Pwestora] Partial rent payment of $formattedPaid received.$refStr Your remaining balance is $formattedRentRemaining. Thank you!";
            }
        }

        // Post official verified receipt into in-app chat thread (never blindly clear violations here)
        try {
            $threadStmt = $pdo->prepare('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?');
            $threadStmt->execute([$pRow['lessor_id'], $pRow['tenant_id']]);
            $threadId = $threadStmt->fetchColumn();
            if (!$threadId) {
                $pdo->prepare('INSERT INTO message_threads (lessor_id, tenant_id) VALUES (?, ?)')->execute([$pRow['lessor_id'], $pRow['tenant_id']]);
                $threadId = (int)$pdo->lastInsertId();
            }
            $remBalStr = $isDepositTopUp
                ? ('₱' . number_format($totalDueAfter, 2))
                : ($isRestructuring ? ($formattedPlanRemaining ?? '₱0.00') : ('₱' . number_format($totalDueAfter, 2)));
            $verifiedMsg = "🧾 OFFICIAL PAYMENT RECEIPT (VERIFIED)\n\nPayment Ref: {$refStr}\nAmount Settled: {$formattedPaid}\nRemaining Balance: {$remBalStr}\nDate: " . date('M d, Y h:i A') . "\nStatus: ✅ VERIFIED & POSTED\n\nYour payment has been officially verified and posted to your account ledger. Thank you!";
            $pdo->prepare("INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, 'lessor', 'Payment Receipt', ?)")
                ->execute([$threadId, $verifiedMsg]);
        } catch (Throwable $ignored) {}

        $res = send_sms($phone, $smsBody);
        return [
            'ok'      => $res['ok'] ?? false,
            'message' => $smsBody,
            'result'  => $res,
        ];
    } catch (Throwable $e) {
        error_log('send_payment_receipt_sms error: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Executes the automated daily rent reminder workflow:
 *  1. 3-day advance pre-due reminder (reminds tenants before due date if rent is still unpaid).
 *  2. Post-grace overdue notice (triggers after grace period, applies ₱500 late fee & multi-month balance).
 *  3. Restructuring installment pre-due reminder (if active plan exists).
 *
 * @param PDO $pdo
 * @param bool $dryRun If true, computes balances and logs actions without dispatching real SMS.
 * @return array Detailed report of execution results.
 */
function run_daily_rent_reminders(PDO $pdo, bool $dryRun = false): array {
    ensure_sms_reminder_logs_table($pdo);

    $report = [
        'timestamp'         => date('Y-m-d H:i:s'),
        'dry_run'           => $dryRun,
        'tenants_checked'   => 0,
        'advance_sent'      => 0,
        'overdue_sent'      => 0,
        'plan_remind_sent'  => 0,
        'already_settled'   => 0,
        'already_reminded'  => 0,
        'skipped_no_phone'  => 0,
        'actions'           => [],
    ];

    // Fetch all active (non-terminated) tenants with contract + application + user profile
    $stmt = $pdo->query(
        "SELECT t.id AS tenant_id, t.tenant_name, t.lessee_id, t.lessor_id, t.due_day,
                t.created_at AS since, t.start_date, t.end_date, t.monthly_rent,
                COALESCE(t.security_deposit, 0) AS security_deposit, t.advance_rent,
                t.status AS tenant_status,
                a.rent AS app_rent, a.term_months AS app_term_months,
                lc.monthly_rent AS contract_rent, lc.term_months AS contract_term_months,
                lc.start_date AS contract_start_date, lc.end_date AS contract_end_date,
                lc.due_day AS contract_due_day, lc.status AS contract_status,
                u.phone AS lessee_phone, u.full_name AS lessee_full_name
         FROM tenants t
         LEFT JOIN applications a ON a.id = t.application_id
         LEFT JOIN lease_contracts lc ON lc.tenant_id = t.id
         LEFT JOIN users u ON u.id = t.lessee_id
         WHERE COALESCE(t.status, 'active') <> 'terminated'
         ORDER BY t.id ASC"
    );
    $tenants = $stmt->fetchAll();

    $today          = new DateTime('today');
    $billingService = new RentBillingService($pdo);

    foreach ($tenants as $t) {
        $report['tenants_checked']++;
        $tenantId = (int) $t['tenant_id'];
        $lessorId = (int) ($t['lessor_id'] ?? 2);
        $phone    = $t['lessee_phone'] ?? '';

        // Authoritative billing + automated Day-5 deposit drawdown + post-grace strike escalation
        $billing             = $billingService->compute($t, !$dryRun, $today);
        $monthlyRent         = (float) $billing['base_rent'];
        $diff                = (int) $billing['due_diff_days'];
        $currentBillingMonth = (string) $billing['active_period'];
        $rentRemaining       = (float) $billing['active_cycle_remaining'];
        $dueDate             = $billing['active_due_date_obj'] ?? new DateTime($billing['due_date'] ?: 'today');

        // Keep chat reminder bubble synced (skip DB writes on dry run)
        if (!$dryRun) {
            sync_chat_rent_reminder(
                $pdo,
                $tenantId,
                $lessorId,
                $diff,
                $monthlyRent,
                $rentRemaining,
                $currentBillingMonth,
                $billing
            );
        }

        if (empty($phone)) {
            $report['skipped_no_phone']++;
            $report['actions'][] = [
                'tenant_id' => $tenantId,
                'name'      => $t['tenant_name'],
                'status'    => 'skipped',
                'reason'    => 'No registered mobile number',
            ];
            continue;
        }

        // Check if there is an active restructuring plan
        $planStmt = $pdo->prepare(
            "SELECT * FROM installment_requests WHERE tenant_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1"
        );
        $planStmt->execute([$tenantId]);
        $activePlan = $planStmt->fetch();

        // -------------------------------------------------------------
        // STAGE 1: 3-Day Advance Pre-Due Reminder
        // -------------------------------------------------------------
        if ($diff === 3 && $rentRemaining > 0.009) {
            $logCheck = $pdo->prepare(
                "SELECT id FROM sms_reminder_logs WHERE tenant_id = ? AND billing_month = ? AND reminder_type = '3_day_advance'"
            );
            $logCheck->execute([$tenantId, $currentBillingMonth]);
            if ($logCheck->fetch()) {
                $report['already_reminded']++;
            } else {
                $formattedRent    = '₱' . number_format($rentRemaining, 2);
                $formattedDueDate = $dueDate->format('M d, Y');
                $advanceMsg       = "[Pwestora] Friendly Reminder: Your monthly rent of $formattedRent is due in 3 days ($formattedDueDate). Please settle your payment on time via the Pwestora app to maintain good standing. Thank you!";

                $smsSuccess = true;
                if (!$dryRun) {
                    $smsRes     = send_sms($phone, $advanceMsg);
                    $smsSuccess = $smsRes['ok'] ?? false;
                    $pdo->prepare(
                        "INSERT INTO sms_reminder_logs (tenant_id, lessee_id, billing_month, reminder_type, amount_due, sent_to, sms_message)
                         VALUES (?, ?, ?, '3_day_advance', ?, ?, ?)"
                    )->execute([
                        $tenantId,
                        $t['lessee_id'],
                        $currentBillingMonth,
                        $rentRemaining,
                        $phone,
                        $advanceMsg,
                    ]);
                }

                $report['advance_sent']++;
                $report['actions'][] = [
                    'tenant_id'  => $tenantId,
                    'name'       => $t['tenant_name'],
                    'type'       => '3_day_advance',
                    'amount'     => $rentRemaining,
                    'message'    => $advanceMsg,
                    'dispatched' => !$dryRun && $smsSuccess,
                ];
            }
        }

        // -------------------------------------------------------------
        // STAGE 2: Post-Grace Overdue Notice (after 5-day grace expires)
        // -------------------------------------------------------------
        if (!empty($billing['is_overdue']) && $billing['outstanding_rent_total'] > 0.009 && !$activePlan) {
            $logCheck = $pdo->prepare(
                "SELECT id FROM sms_reminder_logs WHERE tenant_id = ? AND billing_month = ? AND reminder_type = 'overdue_grace'"
            );
            $logCheck->execute([$tenantId, $currentBillingMonth]);
            if ($logCheck->fetch()) {
                $report['already_reminded']++;
            } else {
                $totalOverdueBalance = (float) $billing['total_due'];
                $fineAmt             = (float) $billing['penalty_amount'];
                $strikeLevel         = (int) $billing['strike_level'];
                $monthsText          = $billing['outstanding_months_label'] ?: $currentBillingMonth;
                $formattedBalance    = '₱' . number_format($totalOverdueBalance, 2);

                if (!empty($billing['is_eviction']) || $strikeLevel >= 3) {
                    $fmtFine    = '₱' . number_format($fineAmt, 2);
                    $overdueMsg = "[Pwestora] EVICTION NOTICE (Strike 3): Your overdue rent ($monthsText) plus $fmtFine penalty brings your balance to $formattedBalance. App access is locked to Payment only until settled.";
                } elseif ($strikeLevel === 2) {
                    $fmtFine    = '₱' . number_format($fineAmt, 2);
                    $overdueMsg = "[Pwestora] URGENT (Strike 2): Your rent ($monthsText) is OVERDUE past the 5-day grace period with a $fmtFine penalty. Total balance due: $formattedBalance. Please settle immediately to avoid Strike 3 Eviction Lock.";
                } else {
                    $overdueMsg = "[Pwestora] URGENT (Strike 1): Your rent ($monthsText) is now OVERDUE after the 5-day grace period (₱0 fine on Strike 1). Total balance due: $formattedBalance. Settle immediately to avoid Strike 2 (₱500 fine).";
                }

                $smsSuccess = true;
                if (!$dryRun) {
                    $smsRes     = send_sms($phone, $overdueMsg);
                    $smsSuccess = $smsRes['ok'] ?? false;
                    $pdo->prepare(
                        "INSERT INTO sms_reminder_logs (tenant_id, lessee_id, billing_month, reminder_type, amount_due, sent_to, sms_message)
                         VALUES (?, ?, ?, 'overdue_grace', ?, ?, ?)"
                    )->execute([
                        $tenantId,
                        $t['lessee_id'],
                        $currentBillingMonth,
                        $totalOverdueBalance,
                        $phone,
                        $overdueMsg,
                    ]);
                }

                $report['overdue_sent']++;
                $report['actions'][] = [
                    'tenant_id'  => $tenantId,
                    'name'       => $t['tenant_name'],
                    'type'       => 'overdue_grace',
                    'amount'     => $totalOverdueBalance,
                    'message'    => $overdueMsg,
                    'dispatched' => !$dryRun && $smsSuccess,
                ];
            }
        }

        // -------------------------------------------------------------
        // STAGE 3: Restructuring Plan Due Reminder (if active)
        // -------------------------------------------------------------
        if ($activePlan && $diff === 3) {
            $planTotal    = (float) $activePlan['amount'];
            $planPaidStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                 WHERE tenant_id = ? AND (note LIKE '%[VERIFIED]%' OR logged_by IS NOT NULL)
                   AND note LIKE '%Restructuring%'
                   AND (note IS NULL OR note NOT LIKE '[DECLINED%')"
            );
            $planPaidStmt->execute([$tenantId]);
            $planPaidSoFar = (float) $planPaidStmt->fetchColumn();
            $planRemaining = max(0.0, $planTotal - $planPaidSoFar);

            if ($planRemaining > 0) {
                $logCheck = $pdo->prepare(
                    "SELECT id FROM sms_reminder_logs WHERE tenant_id = ? AND billing_month = ? AND reminder_type = 'restructure_due'"
                );
                $logCheck->execute([$tenantId, $currentBillingMonth]);
                if ($logCheck->fetch()) {
                    $report['already_reminded']++;
                } else {
                    $formattedPlanRemaining = '₱' . number_format($planRemaining, 2);
                    $planMsg = "[Pwestora] Friendly Reminder: Your restructuring plan installment is due in 3 days. Your remaining balance is $formattedPlanRemaining. Please submit your payment via the Pwestora app. Thank you!";

                    $smsSuccess = true;
                    if (!$dryRun) {
                        $smsRes     = send_sms($phone, $planMsg);
                        $smsSuccess = $smsRes['ok'] ?? false;
                        $pdo->prepare(
                            "INSERT INTO sms_reminder_logs (tenant_id, lessee_id, billing_month, reminder_type, amount_due, sent_to, sms_message)
                             VALUES (?, ?, ?, 'restructure_due', ?, ?, ?)"
                        )->execute([
                            $tenantId,
                            $t['lessee_id'],
                            $currentBillingMonth,
                            $planRemaining,
                            $phone,
                            $planMsg,
                        ]);
                    }

                    $report['plan_remind_sent']++;
                    $report['actions'][] = [
                        'tenant_id'  => $tenantId,
                        'name'       => $t['tenant_name'],
                        'type'       => 'restructure_due',
                        'amount'     => $planRemaining,
                        'message'    => $planMsg,
                        'dispatched' => !$dryRun && $smsSuccess,
                    ];
                }
            }
        }

        if ($billing['outstanding_rent_total'] <= 0.009) {
            $report['already_settled']++;
        }
    }

    // -------------------------------------------------------------
    // STAGE 4: Automated 2-Month Clean Record Strike Reduction
    // -------------------------------------------------------------
    try {
        $decayStmt = $pdo->query(
            "SELECT v.tenant_id, MAX(v.strike) AS current_strike, MAX(v.issued_at) AS last_violation,
                    t.lessor_id, t.tenant_name, u.phone, u.id AS lessee_user_id
             FROM violations v
             JOIN tenants t ON t.id = v.tenant_id
             LEFT JOIN users u ON u.id = t.lessee_id
             WHERE COALESCE(t.status, 'active') <> 'terminated'
               AND (v.is_paid = 0 OR v.is_paid IS NULL)
               AND (v.description IS NULL OR v.description NOT LIKE '%[DECAYED]%')
               AND v.strike > 0
             GROUP BY v.tenant_id"
        );
        $decayCandidates = $decayStmt->fetchAll();

        foreach ($decayCandidates as $cand) {
            $lastViolDate  = new DateTime($cand['last_violation']);
            $daysSinceViol = (int) $today->diff($lastViolDate)->format('%a');

            if ($daysSinceViol >= 60) {
                $overdueCheck = $pdo->prepare(
                    "SELECT COUNT(*) FROM sms_reminder_logs
                     WHERE tenant_id = ? AND reminder_type = 'overdue_grace'
                       AND sent_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)"
                );
                $overdueCheck->execute([$cand['tenant_id']]);
                $hasRecentOverdue = (int) $overdueCheck->fetchColumn() > 0;

                $recentRewardStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM messages m
                     JOIN message_threads mt ON mt.id = m.thread_id
                     WHERE mt.tenant_id = ? AND m.notice_type = 'Good Standing Notice'
                       AND m.sent_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)"
                );
                $recentRewardStmt->execute([$cand['tenant_id']]);
                $hadRecentReward = (int) $recentRewardStmt->fetchColumn() > 0;

                if (!$hasRecentOverdue && !$hadRecentReward) {
                    $oldStrike = (int) $cand['current_strike'];
                    $newStrike = max(0, $oldStrike - 1);

                    if ($dryRun) {
                        $report['actions'][] = [
                            'tenant_id'  => $cand['tenant_id'],
                            'name'       => $cand['tenant_name'],
                            'type'       => 'strike_decay',
                            'amount'     => 0,
                            'message'    => "[DRY RUN] Would reduce strike from $oldStrike to $newStrike after 60 days clean.",
                            'dispatched' => false,
                        ];
                        continue;
                    }

                    // Mark the highest active violation as resolved/decayed with [DECAYED] marker
                    $pdo->prepare(
                        "UPDATE violations
                         SET is_paid = 1,
                             description = CONCAT(COALESCE(description, ''), ' [DECAYED]')
                         WHERE tenant_id = ? AND strike = ? AND (is_paid = 0 OR is_paid IS NULL)
                         ORDER BY id DESC LIMIT 1"
                    )->execute([$cand['tenant_id'], $oldStrike]);

                    $threadStmt = $pdo->prepare('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?');
                    $threadStmt->execute([$cand['lessor_id'], $cand['tenant_id']]);
                    $threadId = $threadStmt->fetchColumn();
                    if (!$threadId) {
                        $pdo->prepare('INSERT INTO message_threads (lessor_id, tenant_id) VALUES (?, ?)')
                            ->execute([$cand['lessor_id'], $cand['tenant_id']]);
                        $threadId = (int) $pdo->lastInsertId();
                    }

                    $decayMsg = "🟢 OFFICIAL NOTICE: GOOD STANDING REWARD\n\nCongratulations! You have maintained a clean record with zero infractions and on-time payments for 2 consecutive months (60 days).\n\nUnder commercial lease policy, your active strike level has been reduced from STRIKE $oldStrike to STRIKE $newStrike.";
                    $pdo->prepare('INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, "lessor", "Good Standing Notice", ?)')
                        ->execute([$threadId, $decayMsg]);

                    if (!empty($cand['phone'])) {
                        send_sms($cand['phone'], "[Pwestora] Good news! You maintained a 2-month clean record with zero infractions. Your strike level was reduced to Strike $newStrike.");
                    }

                    $report['actions'][] = [
                        'tenant_id'  => $cand['tenant_id'],
                        'name'       => $cand['tenant_name'],
                        'type'       => 'strike_decay',
                        'amount'     => 0,
                        'message'    => "Strike reduced from $oldStrike to $newStrike after 60 days clean.",
                        'dispatched' => true,
                    ];
                }
            }
        }
    } catch (Throwable $decayErr) {
        error_log('Strike decay error: ' . $decayErr->getMessage());
    }

    return $report;
}
