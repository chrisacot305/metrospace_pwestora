<?php
/**
 * POST /api/auth_forgot_password.php
 *
 * Self-contained password recovery endpoint via PhilSMS.
 *
 * Actions:
 *   1) send_otp:
 *      Body: { "action": "send_otp", "phone": "09171234567" }
 *   2) verify_otp:
 *      Body: { "action": "verify_otp", "phone": "09171234567", "otp": "123456" }
 *   3) reset_password:
 *      Body: { "action": "reset_password", "phone": "09171234567", "otp": "123456", "new_password": "..." }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\PhoneVerificationRepository;
use Api\Repositories\RateLimitRepository;
use Api\Services\OtpService;
use Api\Services\RateLimiter;

require_once APP_PATH . '/Services/Sms.php'; // format_ph_phone(), send_sms()

Request::requireMethod('POST');

$body   = Request::json();
$action = Request::string($body, 'action', 30);
$phone  = Request::string($body, 'phone', 25);

if ($action === '') {
    $action = 'send_otp';
}

if ($phone === '') {
    json_error('Mobile number is required.');
}

// Clean and normalize phone number
$cleanPhone = preg_replace('/\D+/', '', $phone);
$normalizedPhone = format_ph_phone($phone);
if (!$normalizedPhone) {
    json_error('Please enter a valid 11-digit Philippine mobile number starting with 09 (e.g. 09171234567).');
}

$localPhone = str_starts_with($cleanPhone, '63') ? ('0' . substr($cleanPhone, 2)) : $cleanPhone;
$intPhone   = str_starts_with($cleanPhone, '0') ? ('63' . substr($cleanPhone, 1)) : $cleanPhone;

// Helper: Find user across all common phone formats without depending on repository methods
function findUserByAnyPhone(PDO $pdo, string $raw, string $norm, string $local, string $intl): ?array {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, status, full_name, email, phone FROM users 
             WHERE phone = ? OR phone = ? OR phone = ? OR phone = ? 
                OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?
                OR REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?
             LIMIT 1"
        );
        $stmt->execute([$raw, $norm, $local, $intl, $local, $intl]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (\Throwable $e) {
        error_log('[Forgot Password findUser error] ' . $e->getMessage());
        return null;
    }
}

try {
    $otpCodesRepo = new PhoneVerificationRepository($pdo);
    $rateRepo     = new RateLimitRepository($pdo);
    $otpService   = new OtpService($otpCodesRepo, new RateLimiter($rateRepo));
} catch (\Throwable $e) {
    error_log('[Forgot Password Init Error] ' . $e->getMessage());
    json_error('Server error initializing verification service. Please try again.', 500);
}

// =========================================================================
// ACTION 1: SEND OTP
// =========================================================================
if ($action === 'send_otp') {
    $user = findUserByAnyPhone($pdo, $phone, $normalizedPhone, $localPhone, $intPhone);
    if (!$user) {
        json_error("No registered account was found with mobile number $phone. Please check your number or create an account.", 404);
    }

    if (($user['status'] ?? '') === 'suspended') {
        json_error('Your account has been suspended. Please contact Pwestora support.', 403);
    }

    $blocked = $otpService->sendBlockedReason($normalizedPhone);
    if ($blocked !== null) {
        json_error($blocked, 429);
    }

    $otp = $otpService->issue($normalizedPhone);

    $message = "[Pwestora] Your password reset code is $otp. Valid for " . OtpService::VALID_MINUTES
             . ' minutes. Do not share this code with anyone.';
    $result = send_sms($normalizedPhone, $message);

    if (empty($result['ok'])) {
        $errMsg = $result['error'] ?? 'SMS gateway could not be reached';
        error_log('[SMS Forgot Password Failure] ' . $errMsg);
        json_error("Failed to send SMS code ($errMsg). Please verify your mobile number or try again later.", 500);
    }

    json_ok([
        'message' => 'Password reset code sent successfully.',
        'phone'   => $phone,
    ]);
}

// =========================================================================
// ACTION 2: VERIFY OTP
// =========================================================================
if ($action === 'verify_otp') {
    $otp = Request::string($body, 'otp', 6);
    if (strlen($otp) !== 6) {
        json_error('Please enter a valid 6-digit verification code.');
    }

    if (!$otpService->verify($normalizedPhone, $otp)) {
        json_error('Invalid or expired verification code. Please check the code or request a new one.', 400);
    }

    json_ok([
        'message' => 'Verification code confirmed.',
        'phone'   => $phone,
    ]);
}

// =========================================================================
// ACTION 3: RESET PASSWORD
// =========================================================================
if ($action === 'reset_password') {
    $otp         = Request::string($body, 'otp', 6);
    $newPassword = is_string($body['new_password'] ?? null) ? $body['new_password'] : '';

    if (strlen($otp) !== 6) {
        json_error('Please enter a valid 6-digit verification code.');
    }

    if (strlen($newPassword) < 8) {
        json_error('Password must be at least 8 characters long.');
    }

    if (!$otpService->verify($normalizedPhone, $otp)) {
        json_error('Invalid or expired verification code. Please request a new code.', 400);
    }

    $user = findUserByAnyPhone($pdo, $phone, $normalizedPhone, $localPhone, $intPhone);
    if (!$user) {
        json_error('Account not found.', 404);
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    try {
        $upStmt = $pdo->prepare('UPDATE users SET password_hash = ?, api_token = NULL WHERE id = ?');
        $upStmt->execute([$passwordHash, (int) $user['id']]);
    } catch (\Throwable $e) {
        error_log('[Forgot Password Update Error] ' . $e->getMessage());
        json_error('Could not update password. Please try again.', 500);
    }

    // Consume OTP so it cannot be used again
    $otpService->consume($normalizedPhone);

    json_ok([
        'message' => 'Your password has been successfully reset. You may now log in with your new password.',
    ]);
}

json_error('Invalid action requested.');
