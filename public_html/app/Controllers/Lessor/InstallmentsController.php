<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\InstallmentModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;

require_once APP_PATH . '/Services/Sms.php';

/** Lessor → Installments / rent restructuring (was modules/installments.php) */
final class InstallmentsController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $loggingReq = isset($_GET['log']);

        $installments = new InstallmentModel($this->pdo);
        $tenantsModel = new TenantModel($this->pdo);

        // Approve / Reject
        if ($this->isPost() && isset($_POST['action'], $_POST['request_id'])) {
            Csrf::check();
            $reqId  = (int) $_POST['request_id'];
            $action = $_POST['action'];
            try {
                $reqRow = $installments->findForLessor($reqId, $lessorId);
                if ($reqRow && $reqRow['status'] === 'pending') {
                    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                    $installments->setStatus($reqId, $newStatus);
                    audit_log($this->pdo, $lessorId, ucfirst($newStatus) . ' installment plan', (string) $reqRow['plan_label']);

                    try {
                        $lesseePhone = $tenantsModel->lesseePhone((int) $reqRow['tenant_id']);
                        if (!empty($lesseePhone)) {
                            if ($newStatus === 'approved') {
                                $planName = $reqRow['plan_label'] ?? 'Restructuring';
                                $planAmt  = number_format((float) $reqRow['amount'], 2);
                                send_sms($lesseePhone, "[Pwestora] Good news! Your restructuring arrangement request ($planName for ₱$planAmt) has been APPROVED by your lessor. You can now view your schedule and submit installment payments via the Pwestora app.");
                            } else {
                                send_sms($lesseePhone, "[Pwestora] Notice: Your restructuring arrangement request was declined by your lessor. Please check the Pwestora app or contact your lessor for details.");
                            }
                        }
                    } catch (\Throwable $smsEx) {
                        error_log('Installment decision SMS error: ' . $smsEx->getMessage());
                    }
                }
                echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=installments");}</script>';
                unset($_GET['review']);
            } catch (\Throwable $e) {
                error_log('Installment decision failed: ' . $e->getMessage());
                $error = 'Could not process this request — the error has been logged. Please try again.';
            }
        }

        // Log a new request
        if ($this->isPost() && isset($_POST['log_request'])) {
            Csrf::check();
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $reason   = trim((string) ($_POST['reason'] ?? ''));
            $plan     = $_POST['plan_label'] ?? '';
            $amount   = (float) ($_POST['amount'] ?? 0);

            if (!$tenantsModel->findOwned($tenantId, $lessorId) || $reason === '' || !in_array($plan, ['2 payments', '3 payments'], true) || $amount <= 0) {
                $error = 'Please fill in every field with a valid tenant, plan, and amount.';
            } else {
                try {
                    $installments->create($lessorId, $tenantId, $reason, $plan, $amount);
                    echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=installments");}</script>';
                    $loggingReq = false;
                    unset($_GET['log']);
                } catch (\Throwable $e) {
                    error_log('Log installment request failed: ' . $e->getMessage());
                    $error = 'Could not log this request — the error has been logged. Please try again.';
                }
            }
        }

        $requests   = $installments->listForLessor($lessorId);
        $properties = (new PropertyModel($this->pdo))->listForLessor($lessorId);
        $tenants    = $tenantsModel->listForLessor($lessorId);

        $reviewId = isset($_GET['review']) ? (int) $_GET['review'] : null;
        $reviewing = null;
        if ($reviewId) {
            foreach ($requests as $r) {
                if ((int) $r['id'] === $reviewId) { $reviewing = $r; break; }
            }
        }

        unset($installments, $tenantsModel);
        return $this->view('lessor/installments', get_defined_vars());
    }
}
