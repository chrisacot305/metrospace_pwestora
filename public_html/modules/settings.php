<?php
/**
 * modules/settings.php  (SuperAdmin → "Platform Settings")
 * Controller for Platform Settings.
 */

// --- SAFETY CHECKS ---
$modelPath = __DIR__ . '/../models/SettingsModel.php';
$viewPath = __DIR__ . '/../views/superadmin/settings.php';

if (!file_exists($modelPath)) {
    die("<div class='card' style='margin: 20px; border: 2px solid red; color: red;'><strong>Fatal Error:</strong> Cannot find <b>models/SettingsModel.php</b></div>");
}
if (!file_exists($viewPath)) {
    die("<div class='card' style='margin: 20px; border: 2px solid red; color: red;'><strong>Fatal Error:</strong> Cannot find <b>views/superadmin/settings.php</b></div>");
}
// ---------------------

require_once $modelPath;
$settingsModel = new SettingsModel($pdo);

$adminId = $user['id'];
$successMsg = '';
$error = '';

$TOGGLES = [
    'auto_renewal_offers'    => ['Automated lease renewal offers', 'Trigger 60 days before contract expiration'],
    'qr_audit_required'      => ['Require QR audit for common areas', 'Maintenance staff must scan to log cleaning/inspection'],
    'auto_generate_eviction' => ['Auto-generate eviction notice at Strike 3', 'If off, the lessor must manually trigger the document'],
    'maintenance_mode'       => ['Platform maintenance mode', 'Temporarily blocks new logins platform-wide — use with care'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    try {
        // Process boolean toggles
        foreach ($TOGGLES as $key => $label) {
            $value = isset($_POST[$key]) ? '1' : '0';
            $settingsModel->updateSetting($key, $value);
        }

        // Process commission rate
        $fee = (float) ($_POST['platform_fee_percent'] ?? 5);
        $fee = max(0, min(100, $fee)); // sane bounds
        $settingsModel->updateSetting('platform_fee_percent', (string) $fee);

        audit_log($pdo, $adminId, 'Updated platform settings');
        $successMsg = 'Settings saved successfully.';
    } catch (Throwable $e) {
        error_log('Update settings failed: ' . $e->getMessage());
        $error = 'Could not save settings — the error has been logged. Please try again.';
    }
}

// Fetch Data for the View
$rows = $settingsModel->getAllSettings();

// Load the View
require_once $viewPath;