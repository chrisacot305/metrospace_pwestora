<?php
/**
 * verify_otp.php — Email one-time-code check after registration.
 * Entry point only: the logic lives in app/Controllers/RegistrationController.php (verifyOtp).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\RegistrationController())->verifyOtp();
