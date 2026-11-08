<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\PropertyModel;

require_once APP_PATH . '/Services/Mailer.php';

/** SuperAdmin → Verify Properties (was modules/properties.php) */
final class PropertiesController extends Controller
{
    public function handle(array $user): array
    {
        $error = '';
        $propertyModel = new PropertyModel($this->pdo);

        if ($this->isPost() && isset($_POST['action'], $_POST['property_id'])) {
            Csrf::check();
            $propertyId = (int) $_POST['property_id'];
            $action     = $_POST['action'];

            try {
                $propRow = $propertyModel->getPropertyWithLessor($propertyId);

                if ($action === 'approve') {
                    $propertyModel->updatePropertyStatus($propertyId, 'verified');
                    audit_log($this->pdo, (int) $user['id'], 'Approved property verification', "Property #$propertyId");
                    if ($propRow) send_property_status_email($propRow['email'], $propRow['full_name'], $propRow['name'], true);
                } elseif ($action === 'reject') {
                    $propertyModel->updatePropertyStatus($propertyId, 'rejected');
                    audit_log($this->pdo, (int) $user['id'], 'Rejected property verification', "Property #$propertyId");
                    if ($propRow) send_property_status_email($propRow['email'], $propRow['full_name'], $propRow['name'], false);
                }
                $this->redirect('/dashboard.php?page=properties');
            } catch (\Throwable $e) {
                error_log('Property verification decision failed: ' . $e->getMessage());
                $error = 'Could not process this decision — the error has been logged. Please try again.';
            }
        }

        $allProperties = $propertyModel->getAllProperties();

        $reviewId = isset($_GET['review']) ? (int) $_GET['review'] : null;
        $reviewing = null;
        $reviewingPhotos = [];
        if ($reviewId) {
            foreach ($allProperties as $p) {
                if ((int) $p['id'] === $reviewId) {
                    $reviewing = $p;
                    break;
                }
            }
            if ($reviewing) {
                $reviewingPhotos = $propertyModel->getPropertyPhotos($reviewId);
            }
        }

        $docLabels = [
            'title_doc_path' => 'Land Title',
            'tax_dec_path'   => 'Tax Declaration',
        ];

        return $this->view('superadmin/properties', get_defined_vars());
    }
}
