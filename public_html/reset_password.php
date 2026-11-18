<?php
/**
 * reset_password.php — open the emailed link and choose a new password.
 * Entry point only: the logic lives in app/Controllers/PasswordResetController.php (reset).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\PasswordResetController())->reset();
