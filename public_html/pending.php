<?php
/**
 * pending.php — Shown to lessors who are pending verification or suspended.
 * Entry point only: the logic lives in app/Controllers/AuthController.php (pending).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\AuthController())->pending();
