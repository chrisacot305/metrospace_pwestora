<?php
/**
 * notifications_mark_read.php — Marks all bell notifications as read.
 * Entry point only: the logic lives in app/Controllers/NotificationController.php (markAllRead).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\NotificationController())->markAllRead();
