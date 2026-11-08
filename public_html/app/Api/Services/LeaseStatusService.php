<?php
namespace Api\Services;

use Api\Repositories\DepositRepository;
use Api\Repositories\MessageRepository;
use Api\Repositories\PaymentRepository;
use Api\Repositories\TenantRepository;
use Api\Repositories\TicketRepository;
use Api\Repositories\ViolationRepository;
use Api\Support\AppConfig;
use DateTime;
use PDO;
use Throwable;

/**
 * Builds the lease dashboard for a lessee:
 * rent due date, grace period, penalties, open tickets, and the automatic
 * security-deposit deduction when the grace period has run out.
 */
final class LeaseStatusService
{
    private const GRACE_DAYS = 5;

    private TenantRepository $tenants;
    private TicketRepository $tickets;
    private ViolationRepository $violations;
    private PaymentRepository $payments;
    private DepositRepository $deposits;
    private ChatReceiptService $receipts;

    public function __construct(private PDO $pdo)
    {
        $this->tenants    = new TenantRepository($pdo);
        $this->tickets    = new TicketRepository($pdo);
        $this->violations = new ViolationRepository($pdo);
        $this->payments   = new PaymentRepository($pdo);
        $this->deposits   = new DepositRepository($pdo);
        $this->receipts   = new ChatReceiptService(new MessageRepository($pdo));
    }

    /** All leases of this lessee, newest first, each with computed rent status. */
    public function leasesFor(int $lesseeId): array
    {
        $leases = $this->tenants->leasesForLessee($lesseeId);
        foreach ($leases as $i => $lease) {
            $leases[$i] = $this->buildLease($lease);
        }
        return $leases;
    }

    private function buildLease(array $l): array
    {
        $l['tenant_id']        = (int) $l['tenant_id'];
        $l['property_id']      = (int) ($l['property_id'] ?? 0);
        $l['rent']             = (float) ($l['rent'] ?? 0);
        $l['term_months']      = (int) ($l['term_months'] ?? 0);
        $l['security_deposit'] = (float) ($l['security_deposit'] ?? 0);
        $l['advance_rent']     = (float) ($l['advance_rent'] ?? 0);
        $l['business_name']    = trim(preg_replace('/\s*\([^)]*\)\s*$/', '', $l['business_name'] ?? ''));
        if (!empty($l['cover_photo'])) {
            $l['cover_photo'] = AppConfig::publicUrl($l['cover_photo']);
        }

        $tenantId = $l['tenant_id'];
        $lessorId = (int) $l['lessor_id'];

        $l['open_tickets'] = $this->tickets->countOpenForTenant($tenantId);

        // ---- Penalties from unpaid violations
        $penalties = $this->summarisePenalties($this->violations->unpaidFinesForTenant($tenantId));
        $l['base_rent']         = $l['rent'];
        $l['penalty_amount']    = $penalties['total'];
        $l['penalty_breakdown'] = $penalties['breakdown'];
        $l['total_due']         = $l['base_rent'] + $penalties['total'];
        $l['strike_level']      = $penalties['max_strike'];

        // ---- Due date for this month
        $today        = new DateTime('today');
        $year         = (int) $today->format('Y');
        $month        = (int) $today->format('m');
        $billingMonth = $today->format('Y-m');
        $daysInMonth  = (int) $today->format('t');

        $startDay = !empty($l['since']) ? (int) date('j', strtotime($l['since'])) : 1;
        $rawDueDay = !empty($l['due_day']) ? (int) $l['due_day'] : $startDay;
        $dueDay = min($rawDueDay, $daysInMonth);
        $dueDate = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $dueDay));

        // positive = before due date, 0 = due today, negative = past due
        $diff = (int) $today->diff($dueDate)->format('%r%a');

        // ---- Rent paid so far this month
        $rentPaid = $this->payments->verifiedRentPaidInMonth($tenantId, $month, $year);
        $remaining = max(0.0, $l['base_rent'] - $rentPaid);

        // ---- Grace period expired and rent still unpaid -> take it from the security deposit
        if ($diff <= -self::GRACE_DAYS && $remaining > 0 && $l['security_deposit'] > 0) {
            $result = $this->deductFromSecurityDeposit($tenantId, $lessorId, $l['base_rent'], $month, $year, $billingMonth);
            if ($result !== null) {
                $l['security_deposit'] = $result['new_deposit'];
                $rentPaid  = $result['rent_paid'];
                $remaining = max(0.0, $l['base_rent'] - $rentPaid);
            }
        }

        // Sync in-place chat reminder bubble (no duplicates) — from app/Services/RentNotificationService.php
        sync_chat_rent_reminder($this->pdo, $tenantId, $lessorId, $diff, $l['base_rent'], $remaining, $billingMonth);

        $isSettled = ($remaining <= 0 && $l['base_rent'] > 0);
        $status = $this->dueStatus($diff, $isSettled, $dueDate);

        $l['due_day']                   = $dueDay;
        $l['due_date_formatted']        = $dueDate->format('M d, Y');
        $l['due_diff_days']             = $diff;
        $l['rent_status']               = $status['rent_status'];
        $l['is_grace_period']           = $status['is_grace_period'];
        $l['grace_days_remaining']      = $status['grace_days_remaining'];
        $l['is_overdue']                = $status['is_overdue'];
        $l['is_settled']                = $isSettled;
        $l['due_status_label']          = $status['label'];
        $l['effective_due_date']        = $status['label'];
        $l['rent_paid_this_month']      = $rentPaid;
        $l['rent_remaining_this_month'] = $remaining;

        return $l;
    }

    private function summarisePenalties(array $violations): array
    {
        $total = 0.0;
        $breakdown = [];
        $maxStrike = 0;

        foreach ($violations as $v) {
            $amount = (float) $v['fine_amount'];
            $strike = (int) $v['strike'];
            $maxStrike = max($maxStrike, $strike);
            if ($amount > 0) {
                $total += $amount;
                $breakdown[] = [
                    'violation_id' => (int) $v['id'],
                    'strike'       => $strike,
                    'category'     => $v['category'],
                    'amount'       => $amount,
                    'label'        => "Strike $strike Penalty (" . $v['category'] . ')',
                ];
            }
        }

        return ['total' => $total, 'breakdown' => $breakdown, 'max_strike' => $maxStrike];
    }

    /**
     * Runs inside a transaction with the tenant row locked, so two app refreshes
     * at the same moment can't deduct the deposit twice.
     * Returns ['new_deposit' => float, 'rent_paid' => float] or null if nothing was deducted.
     */
    private function deductFromSecurityDeposit(int $tenantId, int $lessorId, float $baseRent, int $month, int $year, string $billingMonth): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $deposit   = $this->tenants->lockSecurityDeposit($tenantId);
            $rentPaid  = $this->payments->verifiedRentPaidInMonth($tenantId, $month, $year); // re-check after locking
            $remaining = max(0.0, $baseRent - $rentPaid);

            if ($deposit <= 0 || $remaining <= 0) {
                $this->pdo->commit();
                return null;
            }

            $deduct     = min($remaining, $deposit);
            $newDeposit = max(0.0, $deposit - $deduct);
            $refNo      = 'ESC-' . (time() % 10000000);

            $this->tenants->updateSecurityDeposit($tenantId, $newDeposit);
            $this->payments->createFromSecurityDeposit(
                $tenantId, $lessorId, $deduct,
                "[AUTO-DEPOSIT] Auto-settled from Security Deposit escrow on grace period expiry | Ref: $refNo"
            );
            $this->deposits->recordAutomatedRelease(
                $lessorId, $tenantId, $deduct,
                "Auto-deducted to cover $billingMonth rent ($refNo)"
            );

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $this->receipts->postDepositDeduction($lessorId, $tenantId, $refNo, $deduct, $billingMonth);

        return ['new_deposit' => $newDeposit, 'rent_paid' => $rentPaid + $deduct];
    }

    /** Label and flags for the rent countdown shown in the app. */
    private function dueStatus(int $diff, bool $isSettled, DateTime $dueDate): array
    {
        $status = [
            'rent_status'          => 'normal',
            'label'                => '',
            'is_grace_period'      => false,
            'is_overdue'           => false,
            'grace_days_remaining' => self::GRACE_DAYS,
        ];

        if ($isSettled) {
            $status['rent_status'] = 'settled';
            $status['label'] = 'Paid';
            $status['grace_days_remaining'] = 0;
        } elseif ($diff > 3) {
            $status['label'] = $dueDate->format('M d, Y');
        } elseif ($diff === 3) {
            $status['label'] = 'Due in 3 Days';
        } elseif ($diff === 2) {
            $status['label'] = 'Due in 2 Days';
        } elseif ($diff === 1) {
            $status['label'] = 'Due Tomorrow';
        } elseif ($diff === 0) {
            $status['label'] = 'Due Today';
        } elseif ($diff >= -4) {
            $status['rent_status'] = 'grace_period';
            $status['is_grace_period'] = true;
            $status['grace_days_remaining'] = self::GRACE_DAYS + $diff; // -1 => 4 ... -4 => 1
            $status['label'] = $status['grace_days_remaining'] . 'd Grace Left';
        } elseif ($diff === -5) {
            $status['rent_status'] = 'grace_period';
            $status['is_grace_period'] = true;
            $status['grace_days_remaining'] = 1;
            $status['label'] = '⚠️ Last Day of Grace';
        } else {
            $status['rent_status'] = 'overdue';
            $status['is_overdue'] = true;
            $status['grace_days_remaining'] = 0;
            $status['label'] = 'OVERDUE';
        }

        return $status;
    }
}
