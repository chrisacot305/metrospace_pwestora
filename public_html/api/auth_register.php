<?php
/**
 * POST /api/auth_register.php
 * Body (JSON): { "full_name": "...", "email": "...", "phone": "...", "password": "...", "otp_code": "..." }
 * Response: { "ok": true, "token": "...", "user": { id, full_name, email, phone } }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\PhoneVerificationRepository;
use Api\Repositories\RateLimitRepository;
use Api\Repositories\UserRepository;
use Api\Services\OtpService;
use Api\Services\RateLimiter;
use Api\Support\Token;

require_once APP_PATH . '/Services/Sms.php'; // format_ph_phone()

Request::requireMethod('POST');

$body     = Request::json();
$fullName = Request::string($body, 'full_name', 150);
$email    = Request::string($body, 'email', 190);
$phone    = Request::string($body, 'phone', 20);
$password = is_string($body['password'] ?? null) ? $body['password'] : '';
$otpCode  = Request::string($body, 'otp_code', 6);

if ($fullName === '' || $email === '' || strlen($password) < 8) {
    json_error('Full name, email, and a password of at least 8 characters are required.');
}
if (strlen($password) > 72) {
    json_error('Password must be 72 characters or fewer.'); // bcrypt only uses the first 72 bytes
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('That email address looks invalid.');
}
if ($phone === '') {
    json_error('Mobile number is required.');
}

$normalizedPhone = format_ph_phone($phone);
if (!$normalizedPhone) {
    json_error('Please enter a valid 11-digit Philippine mobile number starting with 09.');
}
if ($otpCode === '') {
    json_error('Verification code (OTP) is required.');
}

$otpService = new OtpService(new PhoneVerificationRepository($pdo), new RateLimiter(new RateLimitRepository($pdo)));
if (!$otpService->verify($normalizedPhone, $otpCode)) {
    json_error('Invalid or expired verification code. Please request a new code.');
}

$users = new UserRepository($pdo);
if ($users->emailExists($email)) {
    json_error('An account with that email already exists.', 409);
}

$token  = Token::generate();
$userId = $users->createLessee(
    $fullName,
    $email,
    $phone,
    password_hash($password, PASSWORD_DEFAULT),
    Token::hash($token)
);

$otpService->consume($normalizedPhone);

audit_log($pdo, $userId, 'Lessee registered & phone verified via app', $email);

json_ok([
    'token' => $token,
    'user'  => ['id' => $userId, 'full_name' => $fullName, 'email' => $email, 'phone' => $phone],
], 201);
