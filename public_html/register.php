<?php
/**
 * register.php — Lessor self-registration with document upload.
 * Entry point only: the logic lives in app/Controllers/RegistrationController.php (register).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\RegistrationController())->register();
