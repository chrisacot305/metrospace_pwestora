<?php
/**
 * index.php — Public landing page (login / register modal).
 * Entry point only: the logic lives in app/Controllers/AuthController.php (landing).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\AuthController())->landing();
