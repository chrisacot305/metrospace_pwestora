<?php
/**
 * dashboard.php — Dashboard router: /dashboard.php?page=... (role-based).
 * Entry point only: the logic lives in app/Controllers/DashboardController.php (show).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\DashboardController())->show();
