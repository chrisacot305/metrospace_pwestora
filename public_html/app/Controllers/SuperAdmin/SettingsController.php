<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\SettingsModel;

/** SuperAdmin → Platform Settings (was modules/settings.php) */
final class SettingsController extends Controller
{
    public function handle(array $user): array
    {
        $settingsModel = new SettingsModel($this->pdo);
        $adminId = (int) $user['id'];
        $successMsg = '';
        $error = '';

        $TOGGLES = [
            'auto_renewal_offers'    => ['Automated lease renewal offers', 'Trigger 60 days before contract expiration'],
            'qr_audit_required'      => ['Require QR audit for common areas', 'Maintenance staff must scan to log cleaning/inspection'],
            'auto_generate_eviction' => ['Auto-generate eviction notice at Strike 3', 'If off, the lessor must manually trigger the document'],
            'maintenance_mode'       => ['Platform maintenance mode', 'Temporarily blocks new logins platform-wide — use with care'],
        ];

        if ($this->isPost()) {
            Csrf::check();
            try {
                foreach ($TOGGLES as $key => $label) {
                    $settingsModel->updateSetting($key, isset($_POST[$key]) ? '1' : '0');
                }
                $fee = (float) ($_POST['platform_fee_percent'] ?? 5);
                $fee = max(0, min(100, $fee));
                $settingsModel->updateSetting('platform_fee_percent', (string) $fee);

                audit_log($this->pdo, $adminId, 'Updated platform settings');
                $successMsg = 'Settings saved successfully.';
            } catch (\Throwable $e) {
                error_log('Update settings failed: ' . $e->getMessage());
                $error = 'Could not save settings — the error has been logged. Please try again.';
            }
        }

        $rows = $settingsModel->getAllSettings();

        return $this->view('superadmin/settings', get_defined_vars());
    }
}
