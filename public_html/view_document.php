<?php
/**
 * view_document.php — NEW: protected viewer for lessor verification documents (?file=).
 * Entry point only: the logic lives in app/Controllers/DocumentController.php (verificationFile).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\DocumentController())->verificationFile();
