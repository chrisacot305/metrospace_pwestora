<?php
/**
 * eviction_notice.php — Printable 3rd-strike eviction notice (?id=).
 * Entry point only: the logic lives in app/Controllers/DocumentController.php (evictionNotice).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\DocumentController())->evictionNotice();
