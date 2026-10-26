<?php
/**
 * modules/audit.php  (SuperAdmin → "Audit Logs")
 * Controller for Audit Logs.
 */

// --- SAFETY CHECKS ---
$modelPath = __DIR__ . '/../models/AuditModel.php';
$viewPath = __DIR__ . '/../views/superadmin/audit.php';

if (!file_exists($modelPath)) {
    die("<div class='card' style='margin: 20px; border: 2px solid red; color: red;'><strong>Fatal Error:</strong> Cannot find <b>models/AuditModel.php</b></div>");
}
if (!file_exists($viewPath)) {
    die("<div class='card' style='margin: 20px; border: 2px solid red; color: red;'><strong>Fatal Error:</strong> Cannot find <b>views/superadmin/audit.php</b></div>");
}
// ---------------------

require_once $modelPath;
$auditModel = new AuditModel($pdo);

// Fetch logs (Increased to 500 for better search capability)
$rawLogs = $auditModel->getRecentLogs(500);

$uniqueActions = [];
$logs = array_map(function($l) use (&$uniqueActions) {
    // Format Actor Name
    if (!$l['actor_role']) {
        $l['actor_display'] = 'System';
    } else {
        $l['actor_display'] = $l['actor_role'] === 'lessor' 
            ? ($l['company_name'] ?: 'Lessor') 
            : ($l['full_name'] ?: 'User');
    }
    
    // Collect unique actions for the filter dropdown
    if (!empty($l['action']) && !in_array($l['action'], $uniqueActions)) {
        $uniqueActions[] = $l['action'];
    }
    
    return $l;
}, $rawLogs);

// Sort actions alphabetically for the dropdown
sort($uniqueActions);

// Load View
require_once $viewPath;