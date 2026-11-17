<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\ApplicationModel;
use App\Models\LeaseContractModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;
use App\Services\ContractService;

/**
 * Lessor → Applications (was modules/applications.php)
 * Approving creates the tenant record; rejecting logs the decision;
 * the lessor can reply (counter-proposal) inside the negotiation thread.
 */
final class ApplicationsController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $loggingApp = isset($_GET['log']);

        $apps = new ApplicationModel($this->pdo);
        $propertiesModel = new PropertyModel($this->pdo);

        // Approve / Reject / Rebuttal
        if ($this->isPost() && isset($_POST['action'], $_POST['application_id'])) {
            Csrf::check();
            $appId  = (int) $_POST['application_id'];
            $action = $_POST['action'];
            $app = $apps->findForLessor($appId, $lessorId);

            if ($app && $app['status'] === 'pending') {
                if ($action === 'approve') {
                    $apps->beginTransaction();
                    try {
                        $apps->setStatus($appId, 'approved');
                        // Resolve target move-in from application preferred_move_in or checklist_negotiation metadata
                        $moveIn = !empty($app['preferred_move_in']) ? substr((string) $app['preferred_move_in'], 0, 10) : null;
                        if (!$moveIn && !empty($app['checklist_negotiation'])) {
                            $negotiationData = is_string($app['checklist_negotiation']) ? json_decode($app['checklist_negotiation'], true) : $app['checklist_negotiation'];
                            if (!empty($negotiationData['_proposal_meta']['target_move_in'])) {
                                $moveIn = substr((string) $negotiationData['_proposal_meta']['target_move_in'], 0, 10);
                            }
                        }
                        if (!$moveIn) {
                            $moveIn = date('Y-m-d');
                        }

                        $tenantId = (new TenantModel($this->pdo))->createFromApplication(
                            $lessorId,
                            (int) $app['property_id'],
                            $appId,
                            $app['lessee_id'] !== null ? (int) $app['lessee_id'] : null,
                            $app['tenant_name'],
                            (float) ($app['rent'] ?? 0),
                            $moveIn,
                            (int) date('j', strtotime($moveIn)),
                            $app['business_name'] ?? null
                        );

                        // Auto-initialize contract from the building's assigned contract template
                        try {
                            $contracts = new LeaseContractModel($this->pdo);
                            $contractTpl = ContractService::resolveTemplateForProperty($this->pdo, (int) $app['property_id'], $lessorId);
                            $monthlyRent = (float) ($app['rent'] ?? 0);
                            $termMonths = (int) ($app['term_months'] ?? 12) ?: 12;
                            $start = $moveIn;
                            $end = ContractService::computedEndDate($start, $termMonths, null);
                            $dueDay = (int) date('j', strtotime($start));
                            $deposit = round($monthlyRent * 2, 2);
                            $advance = round($monthlyRent, 2);

                            $terms = [
                                'monthly_rent'     => $monthlyRent,
                                'term_months'      => $termMonths,
                                'start_date'       => $start,
                                'end_date'         => $end,
                                'security_deposit' => $deposit,
                                'advance_rent'     => $advance,
                                'due_day'          => $dueDay,
                            ];

                            $tplId = $contractTpl['id'] > 0 ? (int) $contractTpl['id'] : null;
                            $contracts->create(
                                $tenantId,
                                $lessorId,
                                $tplId,
                                $contractTpl['name'],
                                $terms,
                                json_encode($contractTpl['clauses'], JSON_UNESCAPED_UNICODE),
                                ''
                            );
                            $contracts->finalize($tenantId, $lessorId);

                            // Auto-post the confirmed move-in schedule notice to the chat message thread
                            try {
                                $msgRepo = new \Api\Repositories\MessageRepository($this->pdo);
                                $threadId = $msgRepo->findOrCreateThread($lessorId, $tenantId);
                                $formattedMoveIn = date('F d, Y', strtotime($moveIn));
                                $scheduleNotice = "📅 OFFICIAL NOTICE: TARGET MOVE-IN SCHEDULE CONFIRMED\n\n"
                                    . "Scheduled Turnover Date: {$formattedMoveIn}\n"
                                    . "Commercial Space: " . ($app['property_name'] ?? 'Commercial Space') . "\n"
                                    . "Business Name: " . ($app['business_name'] ?? $app['tenant_name']) . "\n\n"
                                    . "Space turnover and occupancy commencement scheduled for {$formattedMoveIn} under approved commercial lease terms.";
                                $msgRepo->add($threadId, 'lessor', $scheduleNotice, 'Move-In Schedule');
                            } catch (\Throwable $mErr) {
                                error_log('Failed to post Move-In Schedule message: ' . $mErr->getMessage());
                            }
                        } catch (\Throwable $ce) {
                            error_log('Auto contract creation on approve skipped: ' . $ce->getMessage());
                        }
                        audit_log($this->pdo, $lessorId, 'Approved application', $app['tenant_name']);
                        $apps->commit();
                    } catch (\Throwable $e) {
                        $apps->rollBack();
                        error_log('Approve application failed: ' . $e->getMessage());
                        $error = 'Could not approve this application — the error has been logged. '
                               . 'This usually means a recent database update hasn\'t been run yet.';
                    }
                } elseif ($action === 'reject') {
                    $apps->setStatus($appId, 'rejected');
                    audit_log($this->pdo, $lessorId, 'Rejected application', $app['tenant_name']);
                } elseif ($action === 'rebuttal') {
                    $rebuttal = trim((string) ($_POST['lessor_rebuttal'] ?? ''));
                    if ($rebuttal !== '') {
                        $negotiation = [];
                        if (!empty($app['checklist_negotiation'])) {
                            $decoded = json_decode($app['checklist_negotiation'], true);
                            if (is_array($decoded)) $negotiation = $decoded;
                        }
                        if (!isset($negotiation['messages']) || !is_array($negotiation['messages'])) {
                            $negotiation['messages'] = [];
                        }
                        $negotiation['messages'][] = [
                            'sender'      => 'lessor',
                            // (The old code read $user['company_name'], which never exists in the session → always "Lessor".)
                            'sender_name' => $user['company'] ?: 'Lessor',
                            'message'     => $rebuttal,
                            'sent_at'     => date('Y-m-d H:i:s'),
                        ];
                        try {
                            $apps->saveRebuttal($appId, $lessorId, $rebuttal, json_encode($negotiation));
                            audit_log($this->pdo, $lessorId, 'Sent counter-proposal/rebuttal', $app['tenant_name']);
                        } catch (\Throwable $e) {
                            error_log('Rebuttal update failed: ' . $e->getMessage());
                            $error = 'Could not save reply — the error has been logged. Please try again.';
                        }
                    }
                }
            }
            if (!$error) {
                if ($action === 'rebuttal') {
                    echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=applications&review=' . $appId . '");}</script>';
                    $_GET['review'] = $appId;
                } else {
                    echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=applications");}</script>';
                    unset($_GET['review']);
                }
            }
        }

        // Manually log a new application
        if ($this->isPost() && isset($_POST['log_application'])) {
            Csrf::check();
            $propertyId = (int) ($_POST['property_id'] ?? 0);
            $tenantName = trim((string) ($_POST['tenant_name'] ?? ''));
            $business   = trim((string) ($_POST['business_name'] ?? ''));
            $term       = (int) ($_POST['term_months'] ?? 0);
            $rent       = (float) ($_POST['rent'] ?? 0);

            if (!$propertiesModel->isVerifiedAndOwnedBy($propertyId, $lessorId) || $tenantName === '' || $term <= 0 || $rent <= 0) {
                $error = 'Please select a verified property and fill in every field correctly.';
            } else {
                $apps->createManual($propertyId, $lessorId, $tenantName, $business, $term, $rent);
                echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=applications");}</script>';
                $loggingApp = false;
                unset($_GET['log']);
            }
        }

        $applications = $apps->listForLessor($lessorId);
        $verifiedProperties = $propertiesModel->verifiedForLessor($lessorId);

        $reviewId = isset($_GET['review']) ? (int) $_GET['review'] : null;
        $reviewing = null;
        if ($reviewId) {
            foreach ($applications as $a) {
                if ((int) $a['id'] === $reviewId) { $reviewing = $a; break; }
            }
        }

        unset($apps, $propertiesModel);
        return $this->view('lessor/applications', get_defined_vars());
    }
}
