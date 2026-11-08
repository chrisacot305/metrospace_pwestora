<?php
/**
 * GET /api/violations_list.php?tenant_id=5   (tenant_id optional — all of your leases if omitted)
 * Header: Authorization: Bearer <token>
 * Response: { "ok": true, "violations": [ { id, tenant_id, lessor_id, lessor_name, category, strike, acknowledged, issued_at }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Repositories\ViolationRepository;
use Api\Services\TenantResolver;

Request::requireMethod('GET');

$lessee     = require_lessee_auth($pdo);
$tenantId   = Request::positiveInt($_GET['tenant_id'] ?? 0);
$violations = new ViolationRepository($pdo);

if ($tenantId > 0) {
    // Make sure this lease is really yours before listing it.
    $tenant = (new TenantResolver($pdo))->forLessee($lessee, $tenantId);
    if (!$tenant) {
        json_ok(['violations' => []]);
    }
    $rows = $violations->listForTenant((int) $tenant['tenant_id']);
} else {
    $rows = $violations->listForLessee((int) $lessee['id']);
}

json_ok(['violations' => array_map([Formatter::class, 'violation'], $rows)]);
