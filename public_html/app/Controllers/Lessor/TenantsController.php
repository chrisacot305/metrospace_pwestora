<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\TenantModel;
use App\Services\LeaseChecklist;
use App\Services\RentBillingService;
use DateTime;

/**
 * Lessor → Tenants (was modules/tenants.php)
 * Roster with lease status, negotiated checklist, counter-offers and next due date.
 */
final class TenantsController extends Controller
{
    public function handle(array $user): array
    {
        $LEASE_CHECKLIST_ITEMS = LeaseChecklist::items();
        $lessorId = (int) $user['id'];
        $error = '';
        $tenantsModel = new TenantModel($this->pdo);

        // End lease / eviction (Post-Redirect-Get)
        if ($this->isPost() && ($_POST['action'] ?? '') === 'evict') {
            Csrf::check();
            $evictId = (int) ($_POST['tenant_id'] ?? 0);
            try {
                // End date = yesterday → the tenant instantly shows "Lease ended".
                $tenantsModel->endLease($evictId, $lessorId, date('Y-m-d', strtotime('-1 day')));
                $this->redirect('/dashboard.php?page=tenants');
            } catch (\Throwable $e) {
                error_log('Eviction failed: ' . $e->getMessage());
                $error = 'Failed to process eviction — the error has been logged. Please try again.';
            }
        }

        $tenants = $tenantsModel->rosterForLessor($lessorId);
        $contractStatuses = (new \App\Models\LeaseContractModel($this->pdo))->statusesForLessor($lessorId);
        $billingService = new RentBillingService($this->pdo);

        $now = new DateTime();
        $totalActive = count($tenants);
        $expiringSoon = 0;
        $monthlyRentTotal = 0;

        foreach ($tenants as &$t) {
            $billing = $billingService->compute($t, false);
            $hasContract = in_array((string) ($t['contract_status'] ?? ''), ['finalized', 'accepted'], true);

            $t['rent'] = (float) $billing['base_rent'];
            $monthlyRentTotal += $t['rent'];

            $effectiveStart = ($hasContract && !empty($t['contract_start_date']))
                ? $t['contract_start_date']
                : (!empty($t['start_date']) ? $t['start_date'] : $t['created_at']);
            $effectiveEnd = ($hasContract && !empty($t['contract_end_date']))
                ? $t['contract_end_date']
                : ($t['end_date'] ?? null);
            $effectiveTerm = ($hasContract && (int) ($t['contract_term_months'] ?? 0) > 0)
                ? (int) $t['contract_term_months']
                : (int) ($t['term_months'] ?? 0);

            $t['term_months']  = $effectiveTerm;
            $t['lease_end']    = $effectiveEnd ? new DateTime($effectiveEnd) : null;
            $t['status_label'] = 'Active';
            $t['status_style'] = 'background:var(--success-soft); color:var(--success);';
            $t['move_in_date'] = new DateTime($effectiveStart);

            if ($t['lease_end']) {
                $daysLeft = (int) $now->diff($t['lease_end'])->format('%r%a');
                if ($daysLeft <= 30 && $daysLeft >= 0) {
                    $expiringSoon++;
                    $t['status_label'] = 'Expiring (' . $daysLeft . 'd)';
                    $t['status_style'] = 'background:var(--warning-soft); color:var(--warning);';
                } elseif ($daysLeft < 0) {
                    $t['status_label'] = 'Lease ended';
                    $t['status_style'] = 'background:var(--bg); color:var(--ink-500);';
                }
            } elseif ($effectiveTerm > 0) {
                $end = (clone $t['move_in_date']);
                $end->modify('+' . $effectiveTerm . ' months');
                $t['lease_end'] = $end;

                $daysLeft = (int) $now->diff($end)->format('%r%a');
                if ($daysLeft <= 30 && $daysLeft >= 0) {
                    $expiringSoon++;
                    $t['status_label'] = 'Expiring (' . $daysLeft . 'd)';
                    $t['status_style'] = 'background:var(--warning-soft); color:var(--warning);';
                } elseif ($daysLeft < 0) {
                    $t['status_label'] = 'Lease ended';
                    $t['status_style'] = 'background:var(--bg); color:var(--ink-500);';
                }
            }

            if (!empty($billing['is_terminated'])) {
                $t['status_label'] = 'Terminated';
                $t['status_style'] = 'background:var(--bg); color:var(--ink-500);';
            } elseif (!empty($billing['is_eviction'])) {
                $t['status_label'] = 'Eviction Lock (Strike 3)';
                $t['status_style'] = 'background:var(--error-soft, #fee2e2); color:var(--error);';
            }

            $t['last_payment_date']    = !empty($billing['last_paid_at'])
                ? new DateTime($billing['last_paid_at'])
                : (!empty($t['last_paid_at']) ? new DateTime($t['last_paid_at']) : null);
            $t['billing']              = $billing;
            $t['is_overdue']           = (bool) $billing['is_overdue'];
            $t['is_grace_period']      = (bool) $billing['is_grace_period'];
            $t['grace_days_remaining'] = (int) $billing['grace_days_remaining'];

            if ($t['status_label'] === 'Lease ended' && $billing['outstanding_rent_total'] <= 0.009) {
                $t['next_due_date'] = null;
                $t['is_overdue']    = false;
            } else {
                $t['next_due_date'] = $billing['active_due_date_obj'];
            }

            $negotiation = [];
            if (!empty($t['checklist_negotiation'])) {
                $decoded = json_decode($t['checklist_negotiation'], true);
                if (is_array($decoded)) $negotiation = $decoded;
            }

            $clauseKeys = array_filter(array_keys($negotiation), function ($k) { return $k !== 'messages'; });
            $totalClauses = !empty($clauseKeys) ? count($clauseKeys) : count($LEASE_CHECKLIST_ITEMS);
            $counterOffers = [];
            $agreedCount = 0;

            if (empty($clauseKeys)) {
                $agreedCount = $totalClauses;
            } else {
                foreach ($clauseKeys as $key) {
                    $clauseStatus = $negotiation[$key];
                    $isAgreed = true;
                    $note = '';
                    if (is_array($clauseStatus)) {
                        $isAgreed = !isset($clauseStatus['agreed']) || $clauseStatus['agreed'] === true || $clauseStatus['agreed'] === 1 || $clauseStatus['agreed'] === 'true';
                        $note = trim($clauseStatus['rebuttal_note'] ?? $clauseStatus['counter_note'] ?? $clauseStatus['note'] ?? '');
                    }
                    if (!$isAgreed || !empty($note)) {
                        $label = $LEASE_CHECKLIST_ITEMS[$key]['label'] ?? ucwords(str_replace('_', ' ', $key));
                        $counterOffers[] = [
                            'key'   => $key,
                            'label' => $label,
                            'note'  => !empty($note) ? $note : 'Counter-offer proposed.',
                        ];
                    } else {
                        $agreedCount++;
                    }
                }
            }
            $t['checklist_total'] = $totalClauses;
            $t['checklist_agreed'] = $agreedCount;
            $t['counter_offers'] = $counterOffers;
            $t['negotiation'] = $negotiation;
        }
        unset($t);

        $viewTermsId = isset($_GET['view_terms']) ? (int) $_GET['view_terms'] : null;
        $viewingTenant = null;
        if ($viewTermsId) {
            foreach ($tenants as $t) {
                if ((int) $t['id'] === $viewTermsId) { $viewingTenant = $t; break; }
            }
        }

        unset($tenantsModel);
        return $this->view('lessor/tenants', get_defined_vars());
    }
}
