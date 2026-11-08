<?php
/**
 * POST /api/auth_send_otp.php
 * Body (JSON): { "phone": "09171234567", "email": "user@example.com" }
 * Dispatches a 6-digit verification code via PhilSMS.
 *
 * Security: 60-second resend cooldown, max 5 codes per number per hour, 10 per IP per hour.
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\PhoneVerificationRepository;
use Api\Repositories\RateLimitRepository;
use Api\Repositories\UserRepository;
use Api\Services\OtpService;
use Api\Services\RateLimiter;

require_once APP_PATH . '/Services/Sms.php'; // format_ph_phone(), send_sms()

Request::requireMethod('POST');

$body  = Request::json();
$phone = Request::string($body, 'phone', 20);
$email = Request::string($body, 'email', 190);

if ($phone === '') {
    json_error('Mobile number is required.');
}

$normalizedPhone = format_ph_phone($phone);
if (!$normalizedPhone) {
    json_error('Please enter a valid 11-digit Philippine mobile number starting with 09 (e.g. 09171234567).');
}

$users = new UserRepository($pdo);

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Please enter a valid email address.');
    }
    if ($users->emailExists($email)) {
        json_error('An account with that email already exists.', 409);
    }
}

if ($users->phoneExists($phone, $normalizedPhone)) {
    json_error('An account with that mobile number already exists. Please log in instead.', 409);
}

$otpService = new OtpService(new PhoneVerificationRepository($pdo), new RateLimiter(new RateLimitRepository($pdo)));

$blocked = $otpService->sendBlockedReason($normalizedPhone);
if ($blocked !== null) {
    json_error($blocked, 429);
}

$otp = $otpService->issue($normalizedPhone);

$message = "[Pwestora] Your verification code is $otp. Valid for " . OtpService::VALID_MINUTES
         . ' minutes. Do not share this code with anyone.';
$result = send_sms($normalizedPhone, $message);

if (empty($result['ok'])) {
    error_log('[SMS OTP Failure] ' . ($result['error'] ?? 'Unknown gateway error'));
    json_error('Failed to send SMS code. Please verify your mobile number or try again later.', 500);
}

json_ok([
    'message' => 'Verification code sent successfully.',
    'phone'   => $phone,
]);
