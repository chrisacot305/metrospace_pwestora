<?php
/**
 * POST /api/requests_create.php
 * Header: Authorization: Bearer <token>
 * Body: { "reason": "...", "plan_label": "2 payments" | "3 payments", "tenant_id": 5 (optional) }
 * Amount is derived server-side from the tenant's actual monthly rent —
 * never trust a client-supplied amount for something billing-related.
 * Response: { "ok": true, "request_id": 7 }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\InstallmentRequestRepository;
use Api\Services\TenantResolver;

const INSTALLMENT_REASONS = ['Cash flow delay this month', 'Seasonal slowdown in business', 'Unexpected business expense', 'Other'];
const INSTALLMENT_PLANS   = ['2 payments', '3 payments'];

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$body   = Request::json();
$reason = Request::string($body, 'reason', 100);
$plan   = Request::string($body, 'plan_label', 20);

if (!in_array($reason, INSTALLMENT_REASONS, true) || !in_array($plan, INSTALLMENT_PLANS, true)) {
    json_error('A valid reason and plan are required.');
}

$tenant = (new TenantResolver($pdo))->forLessee($lessee, Request::positiveInt($body['tenant_id'] ?? 0));
if (!$tenant) {
    json_error('You need an active lease before requesting an installment plan.', 403);
}

$requestId = (new InstallmentRequestRepository($pdo))->create(
    (int) $tenant['lessor_id'],
    (int) $tenant['tenant_id'],
    $reason,
    $plan,
    (float) $tenant['rent']
);

json_ok(['request_id' => $requestId], 201);
