<?php
/**
 * POST /api/auth_login.php
 * Body (JSON): { "email": "...", "password": "..." }
 * Response: { "ok": true, "token": "...", "user": { id, full_name, email, phone } }
 *
 * Security: max 5 wrong passwords per email and 20 per IP every 15 minutes.
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\RateLimitRepository;
use Api\Repositories\UserRepository;
use Api\Services\AuthService;
use Api\Services\RateLimiter;

Request::requireMethod('POST');

$body     = Request::json();
$email    = Request::string($body, 'email', 190);
$password = is_string($body['password'] ?? null) ? $body['password'] : '';

$limiter  = new RateLimiter(new RateLimitRepository($pdo));
$emailKey = 'login:email:' . strtolower($email);
$ipKey    = 'login:ip:' . Request::clientIp();
$window   = 15 * 60;

if ($limiter->tooManyAttempts($emailKey, 5, $window) || $limiter->tooManyAttempts($ipKey, 20, $window)) {
    json_error('Too many failed login attempts. Please wait 15 minutes and try again.', 429);
}

$users = new UserRepository($pdo);
$user  = $email !== '' ? $users->findLesseeByEmail($email) : null;

if (!$user || !password_verify($password, $user['password_hash'])) {
    $limiter->hit($emailKey, $window);
    $limiter->hit($ipKey, $window);
    json_error('Incorrect email or password.', 401);
}

$limiter->clear($emailKey);

if (($user['status'] ?? '') === 'suspended') {
    json_error('Your account has been suspended. Please contact Pwestora support.', 403);
}

// Fresh token on every login; only its hash is stored in the database.
$token = (new AuthService($users))->issueToken((int) $user['id']);

json_ok([
    'token' => $token,
    'user'  => [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'phone'     => $user['phone'] ?? '',
    ],
]);
