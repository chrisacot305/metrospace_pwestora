<?php
/**
 * POST /api/checklist_submit.php
 * Header: Authorization: Bearer <token>
 * Body: { "acknowledged_keys": ["security_deposit", "rent_escalation", ...] }
 * All keys from checklist_items.php must be present, or this fails —
 * this is meant to be required, not optional, per item.
 * Response: { "ok": true }
 */
require __DIR__ . '/../app/init_api.php';
$LEASE_CHECKLIST_ITEMS = App\Services\LeaseChecklist::items();

use Api\Http\Request;
use Api\Repositories\ChecklistRepository;

Request::requireMethod('POST');

$lessee    = require_lessee_auth($pdo);
$submitted = Request::json()['acknowledged_keys'] ?? [];

if (!is_array($submitted)) {
    json_error('acknowledged_keys must be a list.');
}

$requiredKeys = array_keys($LEASE_CHECKLIST_ITEMS);
$missing = array_diff($requiredKeys, array_filter($submitted, 'is_string'));

if (!empty($missing)) {
    json_error('All checklist items must be acknowledged before continuing.');
}

// Only the server's own keys are saved — never whatever strings the client sent.
(new ChecklistRepository($pdo))->acknowledgeAll((int) $lessee['id'], $requiredKeys);

json_ok([]);
