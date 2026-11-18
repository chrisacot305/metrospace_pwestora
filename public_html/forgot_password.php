<?php
/**
 * forgot_password.php — request a password reset link by email.
 * Entry point only: the logic lives in app/Controllers/PasswordResetController.php (request).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\PasswordResetController())->request();
