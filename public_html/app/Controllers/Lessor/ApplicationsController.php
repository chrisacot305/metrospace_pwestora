<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\ApplicationModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;

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
                        (new TenantModel($this->pdo))->createFromApplication(
                            $lessorId, (int) $app['property_id'], $appId,
                            $app['lessee_id'] !== null ? (int) $app['lessee_id'] : null, $app['tenant_name']
                        );
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
