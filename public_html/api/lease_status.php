<?php
/**
 * GET /api/lease_status.php
 * Header: Authorization: Bearer <token>
 * Response when no active lease:
 *   { "ok": true, "has_lease": false, "user": { ... } }
 * Response when active:
 *   { "ok": true, "has_lease": true, "lease": { ... }, "leases": [ ... ], "user": { ... } }
 *
 * All rent / grace-period / penalty / deposit logic lives in Api\Services\LeaseStatusService.
 */
require __DIR__ . '/../app/init_api.php';
require_once APP_PATH . '/Services/RentNotificationService.php'; // sync_chat_rent_reminder()

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Services\LeaseStatusService;

Request::requireMethod('GET');

$lessee = require_lessee_auth($pdo);
$user   = Formatter::lesseeSummary($lessee);

$leases = (new LeaseStatusService($pdo))->leasesFor((int) $lessee['id']);

if (empty($leases)) {
    json_ok(['has_lease' => false, 'user' => $user]);
}

json_ok([
    'has_lease' => true,
    'lease'     => $leases[0],
    'leases'    => $leases,
    'user'      => $user,
]);
