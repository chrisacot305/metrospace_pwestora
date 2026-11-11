<?php
namespace Api\Services;

use Api\Repositories\TenantRepository;
use Api\Repositories\TicketRepository;
use Api\Support\AppConfig;
use App\Services\RentBillingService;
use PDO;

/**
 * Builds the lease dashboard for a lessee by delegating contract-driven billing,
 * 5-day grace period tracking, automated deposit drawdown, and 3-strike penalties
 * to App\Services\RentBillingService.
 */
final class LeaseStatusService
{
    private TenantRepository $tenants;
    private TicketRepository $tickets;
    private RentBillingService $billing;

    public function __construct(private PDO $pdo)
    {
        $this->tenants = new TenantRepository($pdo);
        $this->tickets = new TicketRepository($pdo);
        $this->billing = new RentBillingService($pdo);
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

        $state = $this->billing->compute($l, true);

        // Sync in-place chat reminder bubble (no duplicates) — from app/Services/RentNotificationService.php
        $hasContract = in_array((string) ($l['contract_status'] ?? ''), ['finalized', 'accepted'], true);
        if ($hasContract && (int) ($l['contract_term_months'] ?? 0) > 0) {
            $l['term_months'] = (int) $l['contract_term_months'];
        }
        if ($hasContract && !empty($l['contract_start_date'])) {
            $l['start_date'] = $l['contract_start_date'];
        }
        if ($hasContract && !empty($l['contract_end_date'])) {
            $l['end_date'] = $l['contract_end_date'];
        }

        sync_chat_rent_reminder(
            $this->pdo,
            $tenantId,
            $lessorId,
            (int) $state['due_diff_days'],
            (float) $state['base_rent'],
            (float) $state['active_cycle_remaining'],
            (string) $state['active_period'],
            $state
        );

        $isSettled = ($state['outstanding_rent_total'] <= 0.009 && $state['base_rent'] > 0 && $state['penalty_amount'] <= 0.009);

        $l['rent']                      = (float) $state['base_rent'];
        $l['base_rent']                 = (float) $state['base_rent'];
        $l['penalty_amount']            = (float) $state['penalty_amount'];
        $l['penalty_breakdown']         = $state['penalty_breakdown'];
        $l['total_due']                 = (float) $state['total_due'];
        $l['strike_level']              = (int) $state['strike_level'];
        $l['escalation_strike']         = (int) $state['escalation_strike'];
        $l['is_eviction']               = (bool) $state['is_eviction'];
        $l['is_terminated']             = (bool) $state['is_terminated'];
        $l['security_deposit']          = (float) $state['security_deposit'];
        $l['auto_deposit_applied']      = (float) $state['auto_deposit_applied'];
        $l['last_paid_at']              = $state['last_paid_at'];

        $l['due_day']                   = (int) $state['due_day'];
        $l['due_date']                  = $state['due_date'];
        $l['due_date_formatted']        = $state['due_date_formatted'];
        $l['due_diff_days']             = (int) $state['due_diff_days'];
        $l['rent_status']               = $state['rent_status'];
        $l['is_grace_period']           = (bool) $state['is_grace_period'];
        $l['grace_days_remaining']      = (int) $state['grace_days_remaining'];
        $l['is_overdue']                = (bool) $state['is_overdue'];
        $l['is_settled']                = $isSettled;
        $l['due_status_label']          = $state['due_status_label'];
        $l['effective_due_date']        = $state['due_status_label'];

        $l['active_period']             = $state['active_period'];
        $l['active_cycle_target']       = (float) $state['active_cycle_target'];
        $l['active_cycle_paid']         = (float) $state['active_cycle_paid'];
        $l['active_cycle_remaining']    = (float) $state['active_cycle_remaining'];
        $l['rent_paid_this_month']      = (float) $state['rent_paid_this_month'];
        $l['rent_remaining_this_month'] = (float) $state['active_cycle_remaining'];
        $l['pending_rent_amount']       = (float) $state['pending_rent_amount'];

        $l['overdue_cycles_count']      = (int) $state['overdue_cycles_count'];
        $l['overdue_rent_amount']       = (float) $state['overdue_rent_amount'];
        $l['next_cycle_rent_amount']    = (float) $state['next_cycle_rent_amount'];
        $l['outstanding_rent_total']    = (float) $state['outstanding_rent_total'];
        $l['outstanding_months_label']  = $state['outstanding_months_label'];
        $l['overdue_option_label']      = $state['overdue_option_label'];

        return $l;
    }
}
