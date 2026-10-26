<?php
/**
 * GET /api/lease_status.php
 * Header: Authorization: Bearer <token>
 * Response when no active lease:
 *   { "ok": true, "has_lease": false }
 * Response when active:
 *   { "ok": true, "has_lease": true, "lease": { ... }, "leases": [ ... ] }
 */
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/payment_sms_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Use GET.', 405);
}

$lessee = require_lessee_auth($pdo);

$stmt = $pdo->prepare(
    "SELECT t.id AS tenant_id, t.property_id, t.lessor_id, t.due_day, t.created_at AS since,
            t.start_date, t.end_date, t.security_deposit, t.advance_rent, t.contract_pdf,
            COALESCE(NULLIF(t.business_name, ''), a.business_name, '') AS business_name,
            t.unit_label,
            p.name AS property_name, p.address AS property_address, p.type AS property_type,
            (SELECT photo_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.id ASC LIMIT 1) AS cover_photo,
            u.company_name AS lessor_name,
            a.rent, a.term_months, a.submitted_at AS applied_at
     FROM tenants t
     JOIN properties p ON p.id = t.property_id
     JOIN users u ON u.id = t.lessor_id
     LEFT JOIN applications a ON a.id = t.application_id
     WHERE t.lessee_id = ?
     ORDER BY t.created_at DESC"
);
$stmt->execute([$lessee['id']]);
$leases = $stmt->fetchAll();

$userPayload = [
    'id'        => (int) $lessee['id'],
    'full_name' => $lessee['full_name'] ?? ($lessee['name'] ?? ''),
    'email'     => $lessee['email'] ?? '',
    'phone'     => $lessee['phone'] ?? '',
];

if (empty($leases)) {
    json_ok(['has_lease' => false, 'user' => $userPayload]);
}

$ticketStmt = $pdo->prepare('SELECT COUNT(*) FROM maintenance_tickets WHERE tenant_id = ? AND stage < 7');

foreach ($leases as &$l) {
    $l['tenant_id'] = (int) $l['tenant_id'];
    $l['property_id'] = (int) ($l['property_id'] ?? 0);
    $l['rent'] = (float) ($l['rent'] ?? 0);
    $l['term_months'] = (int) ($l['term_months'] ?? 0);
    $l['security_deposit'] = (float) ($l['security_deposit'] ?? 0);
    $l['advance_rent'] = (float) ($l['advance_rent'] ?? 0);
    $l['business_name'] = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $l['business_name'] ?? ''));
    if (!empty($l['cover_photo'])) {
        $l['cover_photo'] = APP_URL . '/' . ltrim($l['cover_photo'], '/');
    }
    $ticketStmt->execute([$l['tenant_id']]);
    $l['open_tickets'] = (int) $ticketStmt->fetchColumn();

    // Query active unpaid violation fines dynamically
    try {
        $pdo->exec("ALTER TABLE violations ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $ignored) {}

    $violStmt = $pdo->prepare(
        "SELECT id, category, strike,
                CASE
                    WHEN fine_amount > 0 THEN fine_amount
                    WHEN strike = 2 THEN 500.00
                    WHEN strike >= 3 THEN 1500.00
                    ELSE 0.00
                END AS fine_amount,
                issued_at
         FROM violations
         WHERE tenant_id = ? AND (is_paid = 0 OR is_paid IS NULL) AND strike >= 2
         ORDER BY strike DESC, id DESC"
    );
    $violStmt->execute([$l['tenant_id']]);
    $activeViolations = $violStmt->fetchAll();

    $penaltyTotal = 0.0;
    $penaltyBreakdown = [];
    $maxActiveStrike = 0;
    foreach ($activeViolations as $uv) {
        $amt = (float) $uv['fine_amount'];
        $stk = (int) $uv['strike'];
        if ($stk > $maxActiveStrike) {
            $maxActiveStrike = $stk;
        }
        if ($amt > 0) {
            $penaltyTotal += $amt;
            $penaltyBreakdown[] = [
                'violation_id' => (int) $uv['id'],
                'strike'       => $stk,
                'category'     => $uv['category'],
                'amount'       => $amt,
                'label'        => "Strike $stk Penalty (" . $uv['category'] . ")"
            ];
        }
    }

    $l['base_rent'] = (float) ($l['rent'] ?? 0);
    $l['penalty_amount'] = $penaltyTotal;
    $l['penalty_breakdown'] = $penaltyBreakdown;
    $l['total_due'] = $l['base_rent'] + $penaltyTotal;
    $l['strike_level'] = $maxActiveStrike;

    // Dynamic rent due date & grace period countdown
    $today = new DateTime('today');
    $currentYear  = (int) $today->format('Y');
    $currentMonth = (int) $today->format('m');
    $currentBillingMonth = $today->format('Y-m');
    $daysInMonth  = (int) $today->format('t');

    $startDay = 1;
    if (!empty($l['since'])) {
        $startDay = (int) date('j', strtotime($l['since']));
    }
    $rawDueDay = !empty($l['due_day']) ? (int) $l['due_day'] : $startDay;
    $dueDay = min($rawDueDay, $daysInMonth);
    $dueDateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $dueDay);
    $dueDate = new DateTime($dueDateStr);

    // Days until due date: positive = before due date, 0 = due today, negative = past due
    $diff = (int) $today->diff($dueDate)->format('%r%a');

    // Calculate verified rent paid this month
    $monthRentPaidStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM payments 
         WHERE tenant_id = ? AND (note LIKE '%[VERIFIED]%' OR logged_by IS NOT NULL)
           AND (note LIKE '%Monthly Rent%' OR note NOT LIKE '%Restructuring%')
           AND MONTH(paid_at) = ? AND YEAR(paid_at) = ?"
    );
    $monthRentPaidStmt->execute([$l['tenant_id'], $currentMonth, $currentYear]);
    $rentPaidThisMonth = (float) $monthRentPaidStmt->fetchColumn();
    $rentRemainingThisMonth = max(0.0, $l['base_rent'] - $rentPaidThisMonth);

    // Automated Security Deposit Drawdown upon Grace Period Expiry ($diff <= -5 and rent unpaid)
    $depositBalance = (float) ($l['security_deposit'] ?? 0);
    $lessorId = (int) ($l['lessor_id'] ?? 2);
    if ($diff <= -5 && $rentRemainingThisMonth > 0 && $depositBalance > 0) {
        $deductAmount = min($rentRemainingThisMonth, $depositBalance);
        $newDeposit = max(0.0, $depositBalance - $deductAmount);

        // Update tenant security_deposit balance
        $pdo->prepare('UPDATE tenants SET security_deposit = ? WHERE id = ?')
            ->execute([$newDeposit, $l['tenant_id']]);
        $l['security_deposit'] = $newDeposit;

        // Record automated payment into ledger
        $refNo = 'ESC-' . (time() % 10000000);
        $note = "[AUTO-DEPOSIT] Auto-settled from Security Deposit escrow on grace period expiry | Ref: $refNo";
        $pdo->prepare(
            'INSERT INTO payments (tenant_id, lessor_id, amount, method, note, paid_at, logged_by)
             VALUES (?, ?, ?, "security_deposit", ?, CURDATE(), ?)'
        )->execute([$l['tenant_id'], $lessorId, $deductAmount, $note, $lessorId]);

        // Record in deposits ledger
        $pdo->prepare(
            'INSERT INTO deposits (lessor_id, tenant_id, amount, method, status, note, released_at)
             VALUES (?, ?, ?, "Automated Escrow", "released", ?, NOW())'
        )->execute([$lessorId, $l['tenant_id'], $deductAmount, "Auto-deducted to cover $currentBillingMonth rent ($refNo)"]);

        // Post digital receipt to in-app chat thread
        try {
            $threadStmt = $pdo->prepare('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?');
            $threadStmt->execute([$lessorId, $l['tenant_id']]);
            $threadId = $threadStmt->fetchColumn();
            if ($threadId) {
                $receiptMsg = "🧾 DIGITAL PAYMENT RECEIPT\n\nPayment Ref: {$refNo}\nAmount Covered: ₱" . number_format($deductAmount, 2) . "\nPayment Method: Security Deposit (Escrow)\nDate: " . date('M d, Y h:i A') . "\nStatus: ✅ Settled via Escrow\n\nYour rent for {$currentBillingMonth} was automatically covered using your Security Deposit advance shield.";
                $pdo->prepare("INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, 'system', 'Payment Receipt', ?)")
                    ->execute([$threadId, $receiptMsg]);
            }
        } catch (Throwable $_) {}

        // Recompute remaining rent
        $rentPaidThisMonth += $deductAmount;
        $rentRemainingThisMonth = max(0.0, $l['base_rent'] - $rentPaidThisMonth);
    }

    // Sync in-place chat reminder bubble (no duplicates)
    sync_chat_rent_reminder($pdo, $l['tenant_id'], $lessorId, $diff, $l['base_rent'], $rentRemainingThisMonth, $currentBillingMonth);

    $isSettled = ($rentRemainingThisMonth <= 0 && $l['base_rent'] > 0);
    $isGracePeriod = false;
    $isOverdue = false;
    $graceDaysRemaining = 5;
    $dueStatusLabel = '';

    if ($isSettled) {
        $rentStatus = 'settled';
        $dueStatusLabel = 'Paid';
        $graceDaysRemaining = 0;
    } elseif ($diff > 3) {
        $rentStatus = 'normal';
        $dueStatusLabel = $dueDate->format('M d, Y');
    } elseif ($diff === 3) {
        $rentStatus = 'normal';
        $dueStatusLabel = 'Due in 3 Days';
    } elseif ($diff === 2) {
        $rentStatus = 'normal';
        $dueStatusLabel = 'Due in 2 Days';
    } elseif ($diff === 1) {
        $rentStatus = 'normal';
        $dueStatusLabel = 'Due Tomorrow';
    } elseif ($diff === 0) {
        $rentStatus = 'normal';
        $dueStatusLabel = 'Due Today';
    } elseif ($diff >= -4) {
        $rentStatus = 'grace_period';
        $isGracePeriod = true;
        $graceDaysRemaining = 5 + $diff; // e.g. -1 => 4, -2 => 3, -3 => 2, -4 => 1
        $dueStatusLabel = $graceDaysRemaining . 'd Grace Left';
    } elseif ($diff === -5) {
        $rentStatus = 'grace_period';
        $isGracePeriod = true;
        $graceDaysRemaining = 1;
        $dueStatusLabel = '⚠️ Last Day of Grace';
    } else {
        $rentStatus = 'overdue';
        $isOverdue = true;
        $graceDaysRemaining = 0;
        $dueStatusLabel = 'OVERDUE';
    }

    $l['due_day'] = $dueDay;
    $l['due_date_formatted'] = $dueDate->format('M d, Y');
    $l['due_diff_days'] = $diff;
    $l['rent_status'] = $rentStatus;
    $l['is_grace_period'] = $isGracePeriod;
    $l['grace_days_remaining'] = $graceDaysRemaining;
    $l['is_overdue'] = $isOverdue;
    $l['is_settled'] = $isSettled;
    $l['due_status_label'] = $dueStatusLabel;
    $l['effective_due_date'] = $dueStatusLabel;
    $l['rent_paid_this_month'] = $rentPaidThisMonth;
    $l['rent_remaining_this_month'] = $rentRemainingThisMonth;
}

$primaryLease = $leases[0];

json_ok([
    'has_lease' => true,
    'lease'     => $primaryLease,
    'leases'    => $leases,
    'user'      => $userPayload
]);
