<?php
/**
 * tools/cron_rent_reminders.php — Automated Daily Rent & Overdue SMS Cron Job
 * ===========================================================================
 * Automates:
 *  1. 3-day advance rent reminder SMS (when due in 3 days & unpaid).
 *  2. Post-grace overdue notice SMS (adding ₱500 late fee & computing multi-month balances).
 *  3. Restructuring plan installment pre-due reminders.
 *
 * HOW TO RUN:
 *  - Hostinger hPanel Cron Job (Once daily at 9:00 AM):
 *      0 9 * * * /usr/bin/php /home/u456796838/domains/pwestora.com/public_html/tools/cron_rent_reminders.php >> /home/u456796838/cron.log 2>&1
 *  - CLI Manual execution:
 *      php public_html/tools/cron_rent_reminders.php
 *      php public_html/tools/cron_rent_reminders.php --dry-run
 *  - Web URL trigger (browser or web cron) — only works when CRON_SECRET_KEY is set in .env:
 *      https://pwestora.com/tools/cron_rent_reminders.php?token=<CRON_SECRET_KEY>
 *      https://pwestora.com/tools/cron_rent_reminders.php?token=<CRON_SECRET_KEY>&dry_run=1
 */

// If running in CLI vs Web
$isCli = (PHP_SAPI === 'cli');

define('APP_NO_SESSION', true);
require_once __DIR__ . '/../app/init.php';
require_once APP_PATH . '/Services/RentNotificationService.php';

// Security: over the web, a secret token from .env is REQUIRED.
// (Before, a default token "pwestora_cron_secret" was written in this file's comments,
//  so anyone could trigger mass SMS sending.)
if (!$isCli) {
    $expectedToken = defined('CRON_SECRET_KEY') ? CRON_SECRET_KEY : '';
    $providedToken = $_GET['token'] ?? ($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');

    if (strlen($expectedToken) < 16 || !is_string($providedToken) || !hash_equals($expectedToken, $providedToken)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'ok'    => false,
            'error' => 'Unauthorized.',
        ]);
        exit;
    }
}

// Detect dry-run flag
$dryRun = false;
if ($isCli) {
    global $argv;
    $dryRun = in_array('--dry-run', $argv ?? [], true);
} else {
    $dryRun = !empty($_GET['dry_run']);
}

// Execute the workflow
$report = run_daily_rent_reminders($pdo, $dryRun);

// Output response
if (!$isCli && (($_GET['format'] ?? '') === 'json' || isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'     => true,
        'report' => $report,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Text / HTML output
if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "====================================================\n";
echo "  PWESTORA AUTOMATED RENT & OVERDUE SMS CRON JOB   \n";
echo "====================================================\n";
echo "Execution Time: " . $report['timestamp'] . "\n";
echo "Mode:           " . ($report['dry_run'] ? "DRY RUN (Simulation only, no SMS sent)" : "LIVE DISPATCH") . "\n";
echo "----------------------------------------------------\n";
echo "Tenants Checked:        " . $report['tenants_checked'] . "\n";
echo "3-Day Advance Reminders:" . $report['advance_sent'] . "\n";
echo "Post-Grace Overdue Sent:" . $report['overdue_sent'] . "\n";
echo "Restructure Due Sent:   " . $report['plan_remind_sent'] . "\n";
echo "Already Paid / Settled: " . $report['already_settled'] . "\n";
echo "Already Reminded (Dedup):" . $report['already_reminded'] . "\n";
echo "Skipped (No Phone):     " . $report['skipped_no_phone'] . "\n";
echo "----------------------------------------------------\n";
echo "ACTIONS BREAKDOWN:\n";

if (empty($report['actions'])) {
    echo "  (No notifications scheduled or required today)\n";
} else {
    foreach ($report['actions'] as $idx => $act) {
        $num = $idx + 1;
        echo "  [$num] Tenant: {$act['name']} (ID: {$act['tenant_id']})\n";
        if (isset($act['type'])) {
            echo "      Type:       {$act['type']}\n";
            echo "      Amount:     ₱" . number_format($act['amount'], 2) . "\n";
            echo "      Dispatched: " . ($act['dispatched'] ? "YES" : "NO (dry-run or pending)") . "\n";
            echo "      Message:    {$act['message']}\n";
        } else {
            echo "      Status:     {$act['status']}\n";
            echo "      Reason:     {$act['reason']}\n";
        }
        echo "\n";
    }
}

echo "====================================================\n";
echo "Cron run completed successfully.\n";
