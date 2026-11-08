<?php
/**
 * Older email-OTP registration flow (kept for compatibility).
 *
 * POST /api/register_otp.php?action=send_otp
 *   Body: { "email": "..." }
 * POST /api/register_otp.php?action=verify_otp
 *   Body: { "email": "...", "otp": "123456", "name": "...", "password": "..." }
 *
 * Response: { "success": true|false, "message": "..." }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Http\Response;
use Api\Repositories\EmailVerificationRepository;
use Api\Repositories\RateLimitRepository;
use Api\Repositories\UserRepository;
use Api\Services\RateLimiter;

const EMAIL_OTP_VALID_MINUTES = 10;

/** This older endpoint always answered HTTP 200 with success true/false — kept the same for the app. */
function legacy_reply(bool $success, string $message): void
{
    Response::send(['success' => $success, 'message' => $message]);
}

$action  = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'send_otp';
$data    = Request::json();
$email   = Request::string($data, 'email', 190);
$codes   = new EmailVerificationRepository($pdo);
$limiter = new RateLimiter(new RateLimitRepository($pdo));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    legacy_reply(false, 'A valid email is required');
}

if ($action === 'send_otp') {
    $sendKey = 'email-otp-send:' . strtolower($email);
    $ipKey   = 'email-otp-send:ip:' . Request::clientIp();
    if ($limiter->tooManyAttempts($sendKey, 5, 3600) || $limiter->tooManyAttempts($ipKey, 10, 3600)) {
        legacy_reply(false, 'Too many codes requested. Please try again later.');
    }

    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codes->save($email, $otp);
    $limiter->hit($sendKey, 3600);
    $limiter->hit($ipKey, 3600);

    mail($email, 'Pwestora Verification Code', "Your OTP code is: $otp (valid for " . EMAIL_OTP_VALID_MINUTES . ' minutes)');

    legacy_reply(true, 'OTP sent successfully');
}

if ($action === 'verify_otp') {
    $otp      = Request::string($data, 'otp', 6);
    $name     = Request::string($data, 'name', 150);
    $password = is_string($data['password'] ?? null) ? $data['password'] : '';

    if ($name === '' || strlen($password) < 8 || strlen($password) > 72) {
        legacy_reply(false, 'Name and a password of 8–72 characters are required');
    }

    $guessKey = 'email-otp-guess:' . strtolower($email);
    if ($limiter->tooManyAttempts($guessKey, 5, EMAIL_OTP_VALID_MINUTES * 60)) {
        $codes->delete($email);
        legacy_reply(false, 'Invalid or expired OTP');
    }

    $stored = $codes->findValidCode($email, EMAIL_OTP_VALID_MINUTES);
    if ($stored === null || $otp === '' || !hash_equals($stored, $otp)) {
        $limiter->hit($guessKey, EMAIL_OTP_VALID_MINUTES * 60);
        legacy_reply(false, 'Invalid or expired OTP');
    }

    $users = new UserRepository($pdo);
    if ($users->emailExists($email)) {
        legacy_reply(false, 'An account with that email already exists');
    }

    // Activate user account
    $users->createLessee($name, $email, '', password_hash($password, PASSWORD_DEFAULT), null);
    $codes->delete($email);
    $limiter->clear($guessKey);

    legacy_reply(true, 'Account activated successfully');
}

legacy_reply(false, 'Unknown action');
