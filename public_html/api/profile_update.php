<?php
/**
 * POST /api/profile_update.php
 * Header: Authorization: Bearer <token>
 * Body: { "full_name": "...", "email": "...", "phone": "..." }
 * Updates current lessee's personal information.
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\UserRepository;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$body   = Request::json();

$fullName = Request::string($body, 'full_name', 150, (string) ($lessee['full_name'] ?? ''));
$email    = Request::string($body, 'email', 190, (string) ($lessee['email'] ?? ''));
$phone    = Request::string($body, 'phone', 20, (string) ($lessee['phone'] ?? ''));

if ($fullName === '') {
    json_error('Full name cannot be empty.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Please enter a valid email address.');
}
if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    json_error('Please enter a valid mobile number.');
}

$users = new UserRepository($pdo);
if ($email !== '' && $users->emailExists($email, (int) $lessee['id'])) {
    json_error('That email address is already used by another account.', 409);
}

$users->updateProfile((int) $lessee['id'], $fullName, $email, $phone);

audit_log($pdo, $lessee['id'], 'Updated profile details', "$fullName ($email, $phone)");

json_ok([
    'message' => 'Profile updated successfully.',
    'user'    => [
        'id'        => (int) $lessee['id'],
        'full_name' => $fullName,
        'email'     => $email,
        'phone'     => $phone,
    ],
]);
