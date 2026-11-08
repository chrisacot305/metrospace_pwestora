<?php
/**
 * POST /api/requests_cancel.php
 * Header: Authorization: Bearer <token>
 * Body: { "request_id": 123 }
 * Response: { "ok": true, "cancelled": true }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\InstallmentRequestRepository;
use Api\Repositories\TenantRepository;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$reqId  = Request::positiveInt(Request::json()['request_id'] ?? 0);

if ($reqId <= 0) {
    json_error('A valid request_id is required.');
}

$tenantIds = (new TenantRepository($pdo))->idsForLessee((int) $lessee['id']);
if (empty($tenantIds)) {
    json_error('No tenant account associated with this session.', 404);
}

$requests = new InstallmentRequestRepository($pdo);

if ($requests->deletePending($reqId, $tenantIds)) {
    json_ok(['cancelled' => true]);
}

// Not deleted: either it's already gone, or it's no longer pending.
// Only this lessee's own requests are checked, so other people's request status never leaks.
$status = $requests->statusForTenants($reqId, $tenantIds);
if ($status === null) {
    json_ok(['cancelled' => true]); // already gone — treat as cancelled
}

json_error("Request is in '$status' status and cannot be cancelled.", 400);
