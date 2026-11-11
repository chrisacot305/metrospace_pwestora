<?php
namespace App\Services;

use DateTime;
use PDO;
use Throwable;

/**
 * RentBillingService — single source of truth for contract-driven billing cycles,
 * FIFO rent allocation, 5-day grace countdown, partial/full security-deposit drawdown
 * on the last day of grace, 3-strike overdue escalation, and eviction lock detection.
 *
 * Used by:
 *   - Api\Services\LeaseStatusService        (mobile app API)
 *   - App\Controllers\Lessor\TenantsController (lessor web dashboard)
 *   - RentNotificationService                 (daily cron & payment SMS/receipts)
 */
final class RentBillingService
{
    public const GRACE_DAYS    = 5;
    public const STRIKE_2_FINE = 500.0;
    public const STRIKE_3_FINE = 1500.0;

    public function __construct(private PDO $pdo) {}

    /**
     * Fetches joined tenant + application + contract fields for one tenant or a lessor's roster.
     */
    public function computeForTenantId(int $tenantId, bool $applySideEffects = true, ?DateTime $asOf = null): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id AS tenant_id, t.lessor_id, t.property_id, t.due_day, t.created_at AS since,
                    t.start_date, t.end_date, t.monthly_rent, t.security_deposit, t.advance_rent,
                    t.status AS tenant_status,
                    a.rent AS app_rent, a.term_months AS app_term_months,
                    lc.monthly_rent AS contract_rent, lc.term_months AS contract_term_months,
                    lc.start_date AS contract_start_date, lc.end_date AS contract_end_date,
                    lc.due_day AS contract_due_day, lc.status AS contract_status
             FROM tenants t
             LEFT JOIN applications a ON a.id = t.application_id
             LEFT JOIN lease_contracts lc ON lc.tenant_id = t.id
             WHERE t.id = ? LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->compute($row, $applySideEffects, $asOf) : null;
    }

    /**
     * Computes complete live billing state for a merged tenant row.
     */
    public function compute(array $row, bool $applySideEffects = true, ?DateTime $asOf = null): array
    {
        $tenantId     = (int) ($row['tenant_id'] ?? $row['id'] ?? 0);
        $lessorId     = (int) ($row['lessor_id'] ?? 0);
        $tenantStatus = (string) ($row['tenant_status'] ?? $row['status'] ?? 'active');
        $deposit      = (float) ($row['security_deposit'] ?? 0);
        $today        = ($asOf ? clone $asOf : new DateTime('today'))->setTime(0, 0);

        $terms     = $this->resolveTerms($row, $today);
        $baseRent  = $terms['base_rent'];
        $dueDay    = $terms['due_day'];
        $startDate = $terms['start_date'];
        $endDate   = $terms['end_date'];

        $paymentState = $this->loadPaymentState($tenantId);
        $lastPaidAt   = $paymentState['last_paid_at'];

        if ($baseRent <= 0 || $tenantId <= 0) {
            $penalties = $this->loadPenaltyState($tenantId, $tenantStatus);
            return [
                'base_rent'              => $baseRent,
                'due_day'                => $dueDay,
                'due_date'               => null,
                'due_date_formatted'     => '',
                'due_status_label'       => $dueDay . ' ' . $today->format('M Y'),
                'due_diff_days'          => 0,
                'rent_status'            => $tenantStatus === 'terminated' ? 'terminated' : 'normal',
                'is_grace_period'        => false,
                'is_overdue'             => false,
                'grace_days_remaining'   => 0,
                'active_period'          => $today->format('Y-m'),
                'active_cycle_target'    => $baseRent,
                'active_cycle_paid'      => 0.0,
                'active_cycle_remaining' => 0.0,
                'rent_paid_this_month'   => 0.0,
                'pending_rent_amount'    => $paymentState['pending_rent'],
                'overdue_cycles_count'   => 0,
                'overdue_rent_amount'    => 0.0,
                'next_cycle_rent_amount' => 0.0,
                'outstanding_rent_total' => 0.0,
                'outstanding_months_label' => '',
                'overdue_option_label'   => '',
                'penalty_amount'         => $penalties['penalty_total'],
                'penalty_breakdown'      => $penalties['breakdown'],
                'total_due'              => $penalties['penalty_total'],
                'strike_level'           => $penalties['unresolved_strike'],
                'escalation_strike'      => $penalties['escalation_strike'],
                'is_eviction'            => $penalties['is_eviction'],
                'is_terminated'          => $tenantStatus === 'terminated',
                'security_deposit'       => $deposit,
                'auto_deposit_applied'   => 0.0,
                'last_paid_at'           => $lastPaidAt,
                'active_due_date_obj'    => null,
            ];
        }

        // Build cycles up to current month + 1 upcoming cycle for overdue stacking
        $cycles = $this->generateCycles(
            $startDate,
            $endDate,
            $terms['term_months'],
            $dueDay,
            $today,
            $baseRent,
            $paymentState['paid_months']
        );

        $verifiedPool        = $paymentState['verified_rent_pool'];
        $autoDepositPeriods  = $paymentState['auto_deposit_periods'];
        $autoDepositApplied  = 0.0;

        $this->allocateFifo($cycles, $verifiedPool);

        // Apply last-day-of-grace deposit drawdown and post-grace overdue strikes in chronological order
        if ($applySideEffects && $tenantStatus !== 'terminated') {
            $hasApprovedPlan = $this->hasApprovedRestructuringPlan($tenantId);

            foreach ($cycles as $idx => $c) {
                if ($c['diff'] > -self::GRACE_DAYS || $c['remaining'] <= 0.009) {
                    continue;
                }

                // Step A: Last day of 5-day grace period (diff <= -5): auto-deduct available security deposit once per period
                if ($deposit > 0 && empty($autoDepositPeriods[$c['period']])) {
                    $dedRes = $this->deductDepositForPeriod($tenantId, $lessorId, $c['period'], $c['remaining']);
                    if ($dedRes !== null) {
                        $deposit = $dedRes['new_deposit'];
                        $autoDepositApplied += $dedRes['deducted'];
                        $verifiedPool += $dedRes['deducted'];
                        $autoDepositPeriods[$c['period']] = true;
                        $this->allocateFifo($cycles, $verifiedPool);
                    }
                }

                // Re-read updated remaining after deposit deduction
                $remAfterDeposit = $cycles[$idx]['remaining'];

                // Step B: After grace expires (diff < -5 -> day 6+), if unpaid, record overdue violation strike once per period
                if ($c['diff'] < -self::GRACE_DAYS && $remAfterDeposit > 0.009 && !$hasApprovedPlan) {
                    $this->ensureOverdueViolationRecorded($tenantId, $lessorId, $c['period']);
                }
            }
        }

        // Reload penalty/strike/eviction state after any newly recorded strikes
        $penalties    = $this->loadPenaltyState($tenantId, $tenantStatus);
        $penaltyTotal = $penalties['penalty_total'];

        $overdueCycles = [];
        $graceCycle    = null;
        $currentYm     = $today->format('Y-m');
        $currentCycle  = null;
        $nextUnpaid    = null;

        foreach ($cycles as $c) {
            if ($c['period'] === $currentYm) {
                $currentCycle = $c;
            }
            if ($c['remaining'] > 0.009) {
                if ($c['diff'] < -self::GRACE_DAYS) {
                    $overdueCycles[] = $c;
                } elseif ($c['diff'] <= -1 && $graceCycle === null) {
                    $graceCycle = $c;
                } elseif ($nextUnpaid === null) {
                    $nextUnpaid = $c;
                }
            }
        }

        // Case 1: One or more cycles are past the 5-day grace period -> OVERDUE
        if (!empty($overdueCycles)) {
            $lastOverdue = $overdueCycles[count($overdueCycles) - 1];
            $nextCycle   = $this->findCycleRightAfter($cycles, $lastOverdue['period']);
            $nextCycleRent = ($nextCycle !== null && $nextCycle['remaining'] > 0.009) ? $nextCycle['remaining'] : 0.0;

            $overdueRentOnly = 0.0;
            $monthLabels     = [];
            foreach ($overdueCycles as $oc) {
                $overdueRentOnly += $oc['remaining'];
                $monthLabels[]    = $oc['due_date']->format('F');
            }
            if ($nextCycleRent > 0.009) {
                $monthLabels[] = $nextCycle['due_date']->format('F');
            }

            $oldest         = $overdueCycles[0];
            $outstandingRent = $overdueRentOnly + $nextCycleRent;
            $totalDue        = $outstandingRent + $penaltyTotal;
            $activeTarget    = count($overdueCycles) * $baseRent + ($nextCycleRent > 0 ? $baseRent : 0.0);
            $activePaid      = max(0.0, $activeTarget - $outstandingRent);

            return [
                'base_rent'                => $baseRent,
                'due_day'                  => $dueDay,
                'due_date'                 => $oldest['due_date']->format('Y-m-d'),
                'due_date_formatted'       => $oldest['due_date']->format('j F Y'),
                'due_status_label'         => 'OVERDUE',
                'due_diff_days'            => $oldest['diff'],
                'rent_status'              => $tenantStatus === 'terminated' ? 'terminated' : 'overdue',
                'is_grace_period'          => false,
                'is_overdue'               => true,
                'grace_days_remaining'     => 0,
                'active_period'            => $oldest['period'],
                'active_cycle_target'      => $activeTarget,
                'active_cycle_paid'        => $activePaid,
                'active_cycle_remaining'   => $overdueRentOnly,
                'rent_paid_this_month'     => $activePaid,
                'pending_rent_amount'      => $paymentState['pending_rent'],
                'overdue_cycles_count'     => count($overdueCycles),
                'overdue_rent_amount'      => $overdueRentOnly,
                'next_cycle_rent_amount'   => $nextCycleRent,
                'outstanding_rent_total'   => $outstandingRent,
                'outstanding_months_label' => implode(' + ', array_unique($monthLabels)),
                'overdue_option_label'     => $oldest['due_date']->format('F'),
                'penalty_amount'           => $penaltyTotal,
                'penalty_breakdown'        => $penalties['breakdown'],
                'total_due'                => $totalDue,
                'strike_level'             => $penalties['unresolved_strike'],
                'escalation_strike'        => $penalties['escalation_strike'],
                'is_eviction'              => $penalties['is_eviction'],
                'is_terminated'            => $tenantStatus === 'terminated',
                'security_deposit'         => $deposit,
                'auto_deposit_applied'     => $autoDepositApplied,
                'last_paid_at'             => $lastPaidAt,
                'active_due_date_obj'      => $oldest['due_date'],
            ];
        }

        // Case 2: Currently inside the 5-day grace period -> stays anchored to unpaid grace cycle
        if ($graceCycle !== null) {
            // diff = -1 => 5d Grace ... diff = -5 => 1d Grace
            $graceRemaining = max(1, min(self::GRACE_DAYS, self::GRACE_DAYS + 1 + $graceCycle['diff']));
            $totalDue       = $graceCycle['remaining'] + $penaltyTotal;

            return [
                'base_rent'                => $baseRent,
                'due_day'                  => $dueDay,
                'due_date'                 => $graceCycle['due_date']->format('Y-m-d'),
                'due_date_formatted'       => $graceCycle['due_date']->format('j F Y'),
                'due_status_label'         => "{$graceRemaining}d Grace",
                'due_diff_days'            => $graceCycle['diff'],
                'rent_status'              => $tenantStatus === 'terminated' ? 'terminated' : 'grace_period',
                'is_grace_period'          => true,
                'is_overdue'               => false,
                'grace_days_remaining'     => $graceRemaining,
                'active_period'            => $graceCycle['period'],
                'active_cycle_target'      => $graceCycle['expected'],
                'active_cycle_paid'        => $graceCycle['paid'],
                'active_cycle_remaining'   => $graceCycle['remaining'],
                'rent_paid_this_month'     => $graceCycle['paid'],
                'pending_rent_amount'      => $paymentState['pending_rent'],
                'overdue_cycles_count'     => 0,
                'overdue_rent_amount'      => 0.0,
                'next_cycle_rent_amount'   => 0.0,
                'outstanding_rent_total'   => $graceCycle['remaining'],
                'outstanding_months_label' => '',
                'overdue_option_label'     => '',
                'penalty_amount'           => $penaltyTotal,
                'penalty_breakdown'        => $penalties['breakdown'],
                'total_due'                => $totalDue,
                'strike_level'             => $penalties['unresolved_strike'],
                'escalation_strike'        => $penalties['escalation_strike'],
                'is_eviction'              => $penalties['is_eviction'],
                'is_terminated'            => $tenantStatus === 'terminated',
                'security_deposit'         => $deposit,
                'auto_deposit_applied'     => $autoDepositApplied,
                'last_paid_at'             => $lastPaidAt,
                'active_due_date_obj'      => $graceCycle['due_date'],
            ];
        }

        // Case 3: Current active unpaid cycle (due today or upcoming) OR Settled for current month
        $focus = null;
        if ($currentCycle !== null && $currentCycle['remaining'] > 0.009) {
            $focus = $currentCycle;
        } elseif ($nextUnpaid !== null && $nextUnpaid['period'] <= $currentYm) {
            $focus = $nextUnpaid;
        }

        if ($focus !== null) {
            $diff  = $focus['diff'];
            $label = match (true) {
                $diff === 0 => 'Due Today',
                $diff === 1 => 'Due Tomorrow',
                $diff === 2 => 'Due in 2 Days',
                $diff === 3 => 'Due in 3 Days',
                default     => $focus['due_date']->format('M j, Y'),
            };
            $totalDue = $focus['remaining'] + $penaltyTotal;

            return [
                'base_rent'                => $baseRent,
                'due_day'                  => $dueDay,
                'due_date'                 => $focus['due_date']->format('Y-m-d'),
                'due_date_formatted'       => $focus['due_date']->format('j F Y'),
                'due_status_label'         => $label,
                'due_diff_days'            => $diff,
                'rent_status'              => $tenantStatus === 'terminated' ? 'terminated' : 'normal',
                'is_grace_period'          => false,
                'is_overdue'               => false,
                'grace_days_remaining'     => 0,
                'active_period'            => $focus['period'],
                'active_cycle_target'      => $focus['expected'],
                'active_cycle_paid'        => $focus['paid'],
                'active_cycle_remaining'   => $focus['remaining'],
                'rent_paid_this_month'     => $focus['paid'],
                'pending_rent_amount'      => $paymentState['pending_rent'],
                'overdue_cycles_count'     => 0,
                'overdue_rent_amount'      => 0.0,
                'next_cycle_rent_amount'   => 0.0,
                'outstanding_rent_total'   => $focus['remaining'],
                'outstanding_months_label' => '',
                'overdue_option_label'     => '',
                'penalty_amount'           => $penaltyTotal,
                'penalty_breakdown'        => $penalties['breakdown'],
                'total_due'                => $totalDue,
                'strike_level'             => $penalties['unresolved_strike'],
                'escalation_strike'        => $penalties['escalation_strike'],
                'is_eviction'              => $penalties['is_eviction'],
                'is_terminated'            => $tenantStatus === 'terminated',
                'security_deposit'         => $deposit,
                'auto_deposit_applied'     => $autoDepositApplied,
                'last_paid_at'             => $lastPaidAt,
                'active_due_date_obj'      => $focus['due_date'],
            ];
        }

        // All cycles through the current month are settled!
        $settledRef   = $currentCycle ?? (count($cycles) > 0 ? $cycles[count($cycles) - 1] : null);
        $isLeaseEnded = ($endDate !== null && $today > $endDate);
        $statusCode   = $tenantStatus === 'terminated' ? 'terminated' : ($isLeaseEnded ? 'expired' : 'settled');
        $statusLabel  = $tenantStatus === 'terminated' ? 'Terminated' : ($isLeaseEnded ? 'Lease Ended' : 'Paid');
        $settledDue   = $settledRef ? $settledRef['due_date'] : self::makeDueDate((int) $today->format('Y'), (int) $today->format('m'), $dueDay);

        return [
            'base_rent'                => $baseRent,
            'due_day'                  => $dueDay,
            'due_date'                 => $settledDue->format('Y-m-d'),
            'due_date_formatted'       => $settledDue->format('j F Y'),
            'due_status_label'         => $statusLabel,
            'due_diff_days'            => (int) $today->diff($settledDue)->format('%r%a'),
            'rent_status'              => $statusCode,
            'is_grace_period'          => false,
            'is_overdue'               => false,
            'grace_days_remaining'     => 0,
            'active_period'            => $settledRef ? $settledRef['period'] : $currentYm,
            'active_cycle_target'      => $settledRef ? $settledRef['expected'] : $baseRent,
            'active_cycle_paid'        => $settledRef ? $settledRef['paid'] : $baseRent,
            'active_cycle_remaining'   => 0.0,
            'rent_paid_this_month'     => $settledRef ? $settledRef['paid'] : $baseRent,
            'pending_rent_amount'      => $paymentState['pending_rent'],
            'overdue_cycles_count'     => 0,
            'overdue_rent_amount'      => 0.0,
            'next_cycle_rent_amount'   => 0.0,
            'outstanding_rent_total'   => 0.0,
            'outstanding_months_label' => '',
            'overdue_option_label'     => '',
            'penalty_amount'           => $penaltyTotal,
            'penalty_breakdown'        => $penalties['breakdown'],
            'total_due'                => $penaltyTotal,
            'strike_level'             => $penalties['unresolved_strike'],
            'escalation_strike'        => $penalties['escalation_strike'],
            'is_eviction'              => $penalties['is_eviction'],
            'is_terminated'            => $tenantStatus === 'terminated',
            'security_deposit'         => $deposit,
            'auto_deposit_applied'     => $autoDepositApplied,
            'last_paid_at'             => $lastPaidAt,
            'active_due_date_obj'      => $settledDue,
        ];
    }

    /** Resolves canonical rent, due_day, start_date, end_date, term_months (contract overrides defaults). */
    private function resolveTerms(array $row, DateTime $today): array
    {
        $hasContract = in_array((string) ($row['contract_status'] ?? ''), ['finalized', 'accepted'], true);

        $baseRent = ($hasContract && (float) ($row['contract_rent'] ?? 0) > 0)
            ? (float) $row['contract_rent']
            : (((float) ($row['monthly_rent'] ?? 0) > 0) ? (float) $row['monthly_rent'] : (float) ($row['app_rent'] ?? $row['rent'] ?? 0));

        $startStr = ($hasContract && !empty($row['contract_start_date']))
            ? (string) $row['contract_start_date']
            : (!empty($row['start_date']) ? (string) $row['start_date'] : substr((string) ($row['since'] ?? $row['created_at'] ?? ''), 0, 10));
        $startDate = $startStr !== '' ? (new DateTime($startStr))->setTime(0, 0) : clone $today;

        $termMonths = ($hasContract && (int) ($row['contract_term_months'] ?? 0) > 0)
            ? (int) $row['contract_term_months']
            : ((int) ($row['term_months'] ?? 0) > 0 ? (int) $row['term_months'] : 12);

        $endStr  = ($hasContract && !empty($row['contract_end_date'])) ? (string) $row['contract_end_date'] : (!empty($row['end_date']) ? (string) $row['end_date'] : null);
        $endDate = !empty($endStr) ? (new DateTime($endStr))->setTime(0, 0) : null;

        if ($hasContract && (int) ($row['contract_due_day'] ?? 0) >= 1 && (int) ($row['contract_due_day'] ?? 0) <= 31) {
            $dueDay = (int) $row['contract_due_day'];
        } elseif ((int) ($row['due_day'] ?? 0) >= 1 && (int) ($row['due_day'] ?? 0) <= 31) {
            $dueDay = (int) $row['due_day'];
        } else {
            $dueDay = (int) $startDate->format('j');
        }

        return [
            'base_rent'   => $baseRent,
            'start_date'  => $startDate,
            'term_months' => $termMonths,
            'end_date'    => $endDate,
            'due_day'     => $dueDay,
        ];
    }

    /** Reads verified/pending rent payments properly (handles NULL notes and excludes Top-Ups/Declined). */
    private function loadPaymentState(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, amount, method, note, paid_at, logged_by
             FROM payments
             WHERE tenant_id = ?
             ORDER BY paid_at ASC, id ASC'
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $verifiedPool       = 0.0;
        $pendingRent        = 0.0;
        $paidMonths         = [];
        $autoDepositPeriods = [];
        $lastPaidAt         = null;

        foreach ($rows as $p) {
            $note   = (string) ($p['note'] ?? '');
            $amount = (float) ($p['amount'] ?? 0);
            if ($amount <= 0 || stripos($note, '[DECLINED') !== false) {
                continue;
            }

            $isPending = ($p['logged_by'] === null
                || stripos($note, '[PENDING]') !== false
                || stripos($note, 'pending verification') !== false);

            $isTopUp = stripos($note, 'Security Deposit Top-Up') !== false
                || (stripos($note, 'security_deposit') !== false
                    && stripos($note, '[AUTO-DEPOSIT]') === false
                    && ($p['method'] ?? '') !== 'security_deposit');

            $isPureRestructuring = stripos($note, 'Target: Restructuring Plan') !== false
                || (stripos($note, 'Restructuring') !== false && stripos($note, 'Both') === false);

            if ($isTopUp || $isPureRestructuring) {
                continue;
            }

            if ($isPending) {
                $pendingRent += $amount;
                continue;
            }

            // Deduct any violation fine explicitly settled out of this payment
            $fineSettled = 0.0;
            if (preg_match('/FineSettled:\s*([0-9]+(?:\.[0-9]+)?)/i', $note, $m)) {
                $fineSettled = (float) $m[1];
            }
            $verifiedPool += max(0.0, $amount - $fineSettled);

            if (!empty($p['paid_at'])) {
                $paidYm = substr((string) $p['paid_at'], 0, 7);
                $paidMonths[$paidYm] = true;
                if ($lastPaidAt === null || $p['paid_at'] > $lastPaidAt) {
                    $lastPaidAt = (string) $p['paid_at'];
                }
            }

            if (stripos($note, '[AUTO-DEPOSIT]') !== false) {
                if (preg_match('/Period:\s*(\d{4}-\d{2})/i', $note, $pm)) {
                    $autoDepositPeriods[$pm[1]] = true;
                } elseif (!empty($p['paid_at'])) {
                    // Legacy auto-deposit rows didn't have Period tag; key by paid_at Y-m
                    $autoDepositPeriods[substr((string) $p['paid_at'], 0, 7)] = true;
                }
            }
        }

        // Also inspect deposits table for any automated escrow release covering a specific YYYY-MM period
        try {
            $depStmt = $this->pdo->prepare(
                "SELECT note FROM deposits WHERE tenant_id = ? AND status = 'released' AND method = 'Automated Escrow'"
            );
            $depStmt->execute([$tenantId]);
            while ($dNote = $depStmt->fetchColumn()) {
                if (is_string($dNote) && preg_match('/cover\s+(\d{4}-\d{2})\s+rent/i', $dNote, $dm)) {
                    $autoDepositPeriods[$dm[1]] = true;
                }
            }
        } catch (Throwable $_) {}

        return [
            'verified_rent_pool'   => $verifiedPool,
            'pending_rent'         => $pendingRent,
            'paid_months'          => $paidMonths,
            'auto_deposit_periods' => $autoDepositPeriods,
            'last_paid_at'         => $lastPaidAt,
        ];
    }

    /** Generates billing cycles from lease start through current month + 1 lookahead cycle. */
    private function generateCycles(
        DateTime $startDate,
        ?DateTime $endDate,
        int $termMonths,
        int $dueDay,
        DateTime $today,
        float $baseRent,
        array $paidMonths
    ): array {
        $cursor   = new DateTime($startDate->format('Y-m-01'));
        $firstDue = self::makeDueDate((int) $cursor->format('Y'), (int) $cursor->format('m'), $dueDay);
        if ($firstDue < $startDate && empty($paidMonths[$cursor->format('Y-m')])) {
            $cursor->modify('+1 month');
        }

        $nextMonthLimit = (new DateTime($today->format('Y-m-01')))->modify('+1 month')->format('Y-m');
        $maxCycles      = max(1, min(120, $termMonths > 0 ? $termMonths : 120));
        $cycles         = [];

        for ($i = 0; $i < $maxCycles; $i++) {
            $ym  = $cursor->format('Y-m');
            $due = self::makeDueDate((int) $cursor->format('Y'), (int) $cursor->format('m'), $dueDay);

            if ($ym > $nextMonthLimit) {
                break;
            }
            if ($endDate !== null && $due > $endDate && $ym > $today->format('Y-m')) {
                break;
            }

            $cycles[] = [
                'period'    => $ym,
                'due_date'  => $due,
                'diff'      => (int) $today->diff($due)->format('%r%a'),
                'expected'  => $baseRent,
                'paid'      => 0.0,
                'remaining' => $baseRent,
            ];
            $cursor->modify('+1 month');
        }

        // Ensure at least current calendar month exists if lease hasn't ended yet
        if (empty($cycles) && ($endDate === null || $today <= $endDate)) {
            $due = self::makeDueDate((int) $today->format('Y'), (int) $today->format('m'), $dueDay);
            $cycles[] = [
                'period'    => $today->format('Y-m'),
                'due_date'  => $due,
                'diff'      => (int) $today->diff($due)->format('%r%a'),
                'expected'  => $baseRent,
                'paid'      => 0.0,
                'remaining' => $baseRent,
            ];
        }

        return $cycles;
    }

    /** Allocates total verified rent pool oldest-month-first across generated cycles. */
    private function allocateFifo(array &$cycles, float $pool): void
    {
        $left = max(0.0, $pool);
        foreach ($cycles as &$c) {
            $applied        = min($c['expected'], $left);
            $c['paid']      = $applied;
            $c['remaining'] = max(0.0, $c['expected'] - $applied);
            $left          -= $applied;
        }
        unset($c);
    }

    private function findCycleRightAfter(array $cycles, string $period): ?array
    {
        $found = false;
        foreach ($cycles as $c) {
            if ($found) {
                return $c;
            }
            if ($c['period'] === $period) {
                $found = true;
            }
        }
        return null;
    }

    private function hasApprovedRestructuringPlan(int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM installment_requests WHERE tenant_id = ? AND status = 'approved' LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Atomically deducts available security deposit (full or partial) for a specific billing period.
     * Guarded by FOR UPDATE + period deduplication so refreshes never double-deduct.
     */
    private function deductDepositForPeriod(int $tenantId, int $lessorId, string $period, float $neededAmount): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $depStmt = $this->pdo->prepare('SELECT security_deposit FROM tenants WHERE id = ? FOR UPDATE');
            $depStmt->execute([$tenantId]);
            $deposit = (float) $depStmt->fetchColumn();

            $dupStmt = $this->pdo->prepare(
                "SELECT id FROM payments
                 WHERE tenant_id = ? AND note LIKE '%[AUTO-DEPOSIT]%'
                   AND (note LIKE ? OR DATE_FORMAT(paid_at, '%Y-%m') = ?)
                 LIMIT 1"
            );
            $dupStmt->execute([$tenantId, '%Period: ' . $period . '%', $period]);
            $alreadyInPayments = (bool) $dupStmt->fetchColumn();

            $dupDep = $this->pdo->prepare(
                "SELECT id FROM deposits
                 WHERE tenant_id = ? AND method = 'Automated Escrow' AND note LIKE ?
                 LIMIT 1"
            );
            $dupDep->execute([$tenantId, '%cover ' . $period . ' rent%']);
            $alreadyInDeposits = (bool) $dupDep->fetchColumn();

            if ($deposit <= 0 || $neededAmount <= 0 || $alreadyInPayments || $alreadyInDeposits) {
                $this->pdo->commit();
                return null;
            }

            $deduct     = min($neededAmount, $deposit);
            $newDeposit = max(0.0, $deposit - $deduct);
            $refNo      = 'ESC-' . (time() % 10000000);
            $note       = "[AUTO-DEPOSIT] Auto-settled from Security Deposit on grace expiry | Period: $period | Ref: $refNo";

            $this->pdo->prepare('UPDATE tenants SET security_deposit = ? WHERE id = ?')
                ->execute([$newDeposit, $tenantId]);

            $this->pdo->prepare(
                "INSERT INTO payments (tenant_id, lessor_id, amount, method, note, paid_at, logged_by)
                 VALUES (?, ?, ?, 'security_deposit', ?, CURDATE(), ?)"
            )->execute([$tenantId, $lessorId, $deduct, $note, $lessorId]);

            $this->pdo->prepare(
                "INSERT INTO deposits (lessor_id, tenant_id, amount, method, status, note, released_at)
                 VALUES (?, ?, ?, 'Automated Escrow', 'released', ?, NOW())"
            )->execute([$lessorId, $tenantId, $deduct, "Auto-deducted to cover $period rent ($refNo)"]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('RentBillingService auto-deposit failed: ' . $e->getMessage());
            return null;
        }

        // Post chat receipt notice
        try {
            $threadStmt = $this->pdo->prepare('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?');
            $threadStmt->execute([$lessorId, $tenantId]);
            $threadId = $threadStmt->fetchColumn();
            if ($threadId) {
                $isPartial  = ($neededAmount - $deduct) > 0.009;
                $remText    = $isPartial ? ("\nRemaining Unpaid Balance: ₱" . number_format($neededAmount - $deduct, 2)) : '';
                $receiptMsg = "🧾 DIGITAL PAYMENT RECEIPT\n\nPayment Ref: {$refNo}\nAmount Covered: ₱" . number_format($deduct, 2)
                    . "\nPayment Method: Security Deposit (Escrow)\nBilling Period: {$period}\nDate: " . date('M d, Y h:i A')
                    . "\nStatus: " . ($isPartial ? '⚠️ Partial Escrow Deduction' : '✅ Settled via Escrow')
                    . "{$remText}\n\n"
                    . ($isPartial
                        ? "Your security deposit was deducted on the last day of grace. Remaining rent balance must still be settled to clear arrears."
                        : "Your rent for {$period} was automatically covered using your Security Deposit advance shield.");
                $this->pdo->prepare("INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, 'lessor', 'Payment Receipt', ?)")
                    ->execute([$threadId, $receiptMsg]);
            }
        } catch (Throwable $_) {}

        return ['deducted' => $deduct, 'new_deposit' => $newDeposit];
    }

    /**
     * Records at most ONE 'Unpaid Rent Default (Past Due)' strike violation per unpaid past-grace period.
     * Escalates Strike 1 (₱0) -> Strike 2 (₱500) -> Strike 3 (₱1,500 & Eviction).
     */
    private function ensureOverdueViolationRecorded(int $tenantId, int $lessorId, string $period): void
    {
        try {
            $billRef   = 'BILL-' . $period;
            $checkStmt = $this->pdo->prepare(
                "SELECT id FROM violations
                 WHERE tenant_id = ?
                   AND category LIKE 'Unpaid Rent Default%'
                   AND (contract_ref = ? OR description LIKE ?)
                 LIMIT 1"
            );
            $checkStmt->execute([$tenantId, $billRef, "%$period%"]);
            if ($checkStmt->fetchColumn()) {
                return;
            }

            // Escalate based on active or recent non-decayed strikes
            $maxStmt = $this->pdo->prepare(
                "SELECT COALESCE(MAX(strike), 0) FROM violations
                 WHERE tenant_id = ?
                   AND (is_paid = 0 OR is_paid IS NULL
                        OR (issued_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                            AND (description IS NULL OR description NOT LIKE '%[DECAYED]%')))"
            );
            $maxStmt->execute([$tenantId]);
            $currentStrike = (int) $maxStmt->fetchColumn();
            $newStrike     = min(3, $currentStrike + 1);
            $fineAmount    = ($newStrike === 2) ? self::STRIKE_2_FINE : (($newStrike >= 3) ? self::STRIKE_3_FINE : 0.0);

            $desc = "Monthly rent for $period remained unpaid after the 5-day grace period. Strike $newStrike recorded.";
            $this->pdo->prepare(
                "INSERT INTO violations (lessor_id, tenant_id, category, strike, fine_amount, contract_ref, description, issued_at)
                 VALUES (?, ?, 'Unpaid Rent Default (Past Due)', ?, ?, ?, ?, NOW())"
            )->execute([$lessorId ?: 2, $tenantId, $newStrike, $fineAmount, $billRef, $desc]);

            $this->pdo->prepare("UPDATE tenants SET status = 'overdue' WHERE id = ? AND status IN ('active', 'grace_period')")
                ->execute([$tenantId]);
        } catch (Throwable $e) {
            error_log('RentBillingService overdue strike error: ' . $e->getMessage());
        }
    }

    /** Computes unpaid fines breakdown, active strike level, and eviction lock state. */
    private function loadPenaltyState(int $tenantId, string $tenantStatus): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, category, strike,
                    CASE
                        WHEN fine_amount > 0 THEN fine_amount
                        WHEN strike = 2 THEN 500.00
                        WHEN strike >= 3 THEN 1500.00
                        ELSE 0.00
                    END AS fine_amount
             FROM violations
             WHERE tenant_id = ? AND (is_paid = 0 OR is_paid IS NULL)
             ORDER BY strike DESC, id DESC"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total            = 0.0;
        $breakdown        = [];
        $unresolvedStrike = 0;

        foreach ($rows as $v) {
            $strike = (int) $v['strike'];
            $fine   = (float) $v['fine_amount'];
            if ($strike > $unresolvedStrike) {
                $unresolvedStrike = $strike;
            }
            if ($fine > 0) {
                $total += $fine;
                $breakdown[] = [
                    'violation_id' => (int) $v['id'],
                    'strike'       => $strike,
                    'category'     => $v['category'],
                    'amount'       => $fine,
                    'label'        => "Strike $strike Penalty (" . $v['category'] . ')',
                ];
            }
        }

        // Highest non-decayed strike (for escalation reference)
        $escStmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(strike), 0) FROM violations
             WHERE tenant_id = ?
               AND (is_paid = 0 OR is_paid IS NULL
                    OR (issued_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                        AND (description IS NULL OR description NOT LIKE '%[DECAYED]%')))"
        );
        $escStmt->execute([$tenantId]);
        $escalationStrike = (int) $escStmt->fetchColumn();

        $isEviction = ($unresolvedStrike >= 3 || $tenantStatus === 'terminated');

        return [
            'penalty_total'     => $total,
            'breakdown'         => $breakdown,
            'unresolved_strike' => $unresolvedStrike,
            'escalation_strike' => $escalationStrike,
            'is_eviction'       => $isEviction,
        ];
    }

    public static function makeDueDate(int $year, int $month, int $dueDay): DateTime
    {
        $first = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $days  = (int) $first->format('t');
        $day   = max(1, min($dueDay, $days));
        return (new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day)))->setTime(0, 0);
    }
}
