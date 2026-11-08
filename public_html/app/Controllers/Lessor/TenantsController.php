<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\TenantModel;
use App\Services\LeaseChecklist;
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

        $now = new DateTime();
        $today = new DateTime('today');
        $totalActive = count($tenants);
        $expiringSoon = 0;
        $monthlyRentTotal = 0;

        foreach ($tenants as &$t) {
            $t['rent'] = (float) ($t['rent'] ?? 0);
            $monthlyRentTotal += $t['rent'];

            $t['lease_end'] = $t['end_date'] ? new DateTime($t['end_date']) : null;
            $t['status_label'] = 'Active';
            $t['status_style'] = 'background:var(--success-soft); color:var(--success);';
            $t['move_in_date'] = $t['start_date'] ? new DateTime($t['start_date']) : new DateTime($t['created_at']);

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
            } elseif ($t['term_months']) {
                // No end date but a term exists → compute the end date.
                $end = (clone $t['move_in_date']);
                $end->modify('+' . (int) $t['term_months'] . ' months');
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

            $t['last_payment_date'] = !empty($t['last_paid_at']) ? new DateTime($t['last_paid_at']) : null;
            $baseDueDate = $t['last_payment_date'] ? clone $t['last_payment_date'] : clone $t['move_in_date'];
            $nextDue = (clone $baseDueDate)->modify('+1 month');

            if ($t['status_label'] === 'Lease ended') {
                $t['next_due_date'] = null;
                $t['is_overdue'] = false;
            } else {
                $t['next_due_date'] = $nextDue;
                $t['is_overdue'] = $nextDue < $today;
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
