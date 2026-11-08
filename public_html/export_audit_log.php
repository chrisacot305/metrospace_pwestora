<?php
/**
 * export_audit_log.php — Audit log CSV download (Super Admin).
 * Entry point only: the logic lives in app/Controllers/ReportController.php (exportAuditLog).
 */
require __DIR__ . '/app/init.php';

(new App\Controllers\ReportController())->exportAuditLog();
