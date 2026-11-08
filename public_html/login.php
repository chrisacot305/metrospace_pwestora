<?php
/**
 * login.php — Login for lessors and the Super Admin.
 * Entry point only: the logic lives in app/Controllers/AuthController.php (login).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\AuthController())->login();
