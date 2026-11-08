<?php
/**
 * tools/test_sms.php — Quick CLI/Web test tool for PhilSMS
 * Usage via CLI: php tools/test_sms.php 09171234567
 */
// Command line ONLY. (Before, anyone could open this in a browser and send SMS from your account.)
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('APP_NO_SESSION', true);
require_once __DIR__ . '/../app/init.php';
require_once APP_PATH . '/Services/Sms.php';

$phone = $argv[1] ?? null;

if (!$phone) {
    echo "Usage: php tools/test_sms.php <mobile_number>\n";
    echo "Example: php tools/test_sms.php 09171234567\n";
    exit(1);
}

echo "Testing PhilSMS dispatch to: $phone\n";
$msg = "[Pwestora Test] Verification test from Pwestora SMS Gateway. Time: " . date('H:i:s');
$result = send_sms($phone, $msg);

echo "\nResult:\n";
print_r($result);
echo "\n";
