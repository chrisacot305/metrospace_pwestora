<?php
/**
 * GET /api/requests_list.php?tenant_id=5   (tenant_id optional — newest lease if omitted)
 * Header: Authorization: Bearer <token>
 * Response: { "ok": true, "requests": [ { id, reason, plan_label, amount, status, created_at }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Repositories\InstallmentRequestRepository;
use Api\Services\TenantResolver;

Request::requireMethod('GET');

$lessee = require_lessee_auth($pdo);

$tenant = (new TenantResolver($pdo))->forLessee($lessee, Request::positiveInt($_GET['tenant_id'] ?? 0));
if (!$tenant) {
    json_ok(['requests' => []]);
}

$requests = array_map(
    [Formatter::class, 'installmentRequest'],
    (new InstallmentRequestRepository($pdo))->listForTenant((int) $tenant['tenant_id'])
);

json_ok(['requests' => $requests]);
