<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\LessorModel;

/** SuperAdmin → Verify Lessors (was modules/lessors.php) */
final class LessorsController extends Controller
{
    public function handle(array $user): array
    {
        $lessorModel = new LessorModel($this->pdo);
        $adminId = (int) $user['id'];
        $error = '';

        $docLabels = [
            'business_permit_path' => 'Business Permit',
            'sec_dti_path'         => 'SEC / DTI Registration',
            'bir_cert_path'        => 'BIR Certificate',
            'land_title_path'      => 'Land Title',
            'gov_id_path'          => 'Government ID',
        ];

        // Approve / Reject
        if ($this->isPost()) {
            Csrf::check();
            $action   = $_POST['action'] ?? '';
            $targetId = (int) ($_POST['lessor_id'] ?? 0);

            if ($targetId > 0) {
                try {
                    if ($action === 'approve') {
                        $lessorModel->approveLessor($targetId, $adminId);
                        audit_log($this->pdo, $adminId, "Approved lessor ID $targetId");
                    } elseif ($action === 'reject') {
                        $lessorModel->rejectLessor($targetId);
                        audit_log($this->pdo, $adminId, "Rejected lessor ID $targetId");
                    }
                    $this->redirect('/dashboard.php?page=lessors');
                } catch (\Throwable $e) {
                    error_log('Lessor verification error: ' . $e->getMessage());
                    $error = 'Could not process this decision — the error has been logged. Please try again.';
                }
            }
        }

        $reviewing = null;
        if (isset($_GET['review'])) {
            $reviewing = $lessorModel->getLessorById((int) $_GET['review']);
        }

        $allLessors = $lessorModel->getAllLessors();

        return $this->view('superadmin/lessors', get_defined_vars());
    }
}
