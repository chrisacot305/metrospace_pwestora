<?php
/**
 * GET /api/payments_list.php?tenant_id=5   (tenant_id optional — newest lease if omitted)
 * Header: Authorization: Bearer <token>
 * Response: { "ok": true, "payments": [ { id, amount, method, note, paid_at, status, reference_no, target }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Repositories\PaymentRepository;
use Api\Services\TenantResolver;

Request::requireMethod('GET');

$lessee = require_lessee_auth($pdo, true);

$tenant = (new TenantResolver($pdo))->forBilling($lessee, Request::positiveInt($_GET['tenant_id'] ?? 0));
if (!$tenant) {
    json_ok(['payments' => []]);
}

$payments = array_map(
    [Formatter::class, 'payment'],
    (new PaymentRepository($pdo))->listForTenant((int) $tenant['tenant_id'])
);

json_ok(['payments' => $payments]);
