<?php
/**
 * GET /api/profile_me.php
 * Header: Authorization: Bearer <token>
 * Returns current user profile details including phone and email.
 */
require __DIR__ . '/../app/init_api.php';

$user = require_lessee_auth($pdo, true);

json_ok([
    'user' => [
        'id'        => (int) $user['id'],
        'full_name' => $user['full_name'] ?? ($user['name'] ?? ''),
        'email'     => $user['email'] ?? '',
        'phone'     => $user['phone'] ?? '',
        'role'      => $user['role'] ?? 'lessee',
        'status'    => $user['status'] ?? 'active',
    ],
]);
