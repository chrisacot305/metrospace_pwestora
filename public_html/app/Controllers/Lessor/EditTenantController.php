<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Validator;
use App\Models\TenantModel;
use App\Models\UserModel;

/**
 * Lessor → Edit Tenant (was modules/edit_tenant.php)
 * Update lease terms (move-in / end dates, rent, due day) and contact details.
 */
final class EditTenantController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $tenantId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $error = '';
        $success = '';

        $tenantsModel = new TenantModel($this->pdo);
        $tenant = $tenantsModel->findWithContact($tenantId, $lessorId);

        if (!$tenant) {
            return ['inline_error' => "<div class='error-msg'>Tenant not found or access denied.</div>"];
        }

        if ($this->isPost()) {
            Csrf::check(); // (this form had no CSRF protection before)

            $tenantName   = trim((string) ($_POST['tenant_name'] ?? ''));
            $businessName = trim((string) ($_POST['business_name'] ?? ''));
            $startDate    = !empty($_POST['start_date']) ? Validator::date((string) $_POST['start_date']) : null;
            $endDate      = !empty($_POST['end_date']) ? Validator::date((string) $_POST['end_date']) : null;
            $monthlyRent  = (float) ($_POST['monthly_rent'] ?? 0);
            $dueDay       = (int) ($_POST['due_day'] ?? 30);
            $email        = trim((string) ($_POST['email'] ?? ''));
            $phone        = trim((string) ($_POST['phone'] ?? ''));

            if (empty($tenantName)) {
                $error = 'Tenant Name is required.';
            } elseif ($email !== '' && !Validator::email($email)) {
                $error = 'Please enter a valid email address.';
            } elseif ($dueDay < 1 || $dueDay > 31) {
                $error = 'Due day must be between 1 and 31.';
            } else {
                // Registered app users keep their contact info on their account, not in the offline columns.
                $tenantsModel->updateDetails($tenantId, $lessorId, [
                    'tenant_name'   => $tenantName,
                    'business_name' => $businessName,
                    'offline_email' => $tenant['lessee_id'] ? null : $email,
                    'offline_phone' => $tenant['lessee_id'] ? null : $phone,
                    'start_date'    => $startDate,
                    'end_date'      => $endDate,
                    'monthly_rent'  => $monthlyRent,
                    'due_day'       => $dueDay,
                ]);

                if ($tenant['lessee_id']) {
                    (new UserModel($this->pdo))->updateContact((int) $tenant['lessee_id'], $email, $phone);
                }

                $success = 'Tenant details updated successfully.';
                $tenant = $tenantsModel->findWithContact($tenantId, $lessorId);
            }
        }

        unset($tenantsModel);
        return $this->view('lessor/edit_tenant', get_defined_vars());
    }
}
