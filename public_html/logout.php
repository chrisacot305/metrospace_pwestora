<?php
/**
 * logout.php — Ends the session and returns to the landing page.
 * Entry point only: the logic lives in app/Controllers/AuthController.php (logout).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\AuthController())->logout();
