<?php
/**
 * POST /api/contract_accept.php
 * Header: Authorization: Bearer <token>
 * Body: { "tenant_id": 5, "version": 2 }
 *   version = the "version" the app showed (from contract_view). If the lessor revised the
 *   contract in the meantime, the accept is refused so the tenant never accepts terms they didn't see.
 * Response: { "ok": true, "status": "accepted", "accepted_at": "2026-11-08 19:40:00" }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\MessageRepository;
use Api\Repositories\TenantRepository;
use App\Models\LeaseContractModel;
use App\Models\NotifyModel;
use App\Services\ContractService;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$body   = Request::json();

$tenantId = Request::positiveInt($body['tenant_id'] ?? 0);
$version  = Request::positiveInt($body['version'] ?? 0);
if ($tenantId <= 0 || $version <= 0) {
    json_error('tenant_id and version are required.');
}

// The lease must belong to this lessee.
$tenancy = (new TenantRepository($pdo))->findForLessee((int) $lessee['id'], $tenantId);
if (!$tenancy) {
    json_error('Lease not found.', 404);
}

$contracts = new LeaseContractModel($pdo);
$contract  = $contracts->findByTenant($tenantId);

if (!$contract || $contract['status'] === ContractService::STATUS_DRAFT) {
    json_error('There is no finalized contract to accept yet.', 409);
}
if ($contract['status'] === ContractService::STATUS_ACCEPTED) {
    json_ok(['status' => 'accepted', 'accepted_at' => $contract['accepted_at'], 'already' => true]);
}
if ((int) $contract['version'] !== $version) {
    json_error('The lessor has updated this contract. Please reload it and review the latest version.', 409);
}
if (!$contracts->accept($tenantId, $version)) {
    json_error('Could not accept the contract. Please reload and try again.', 409);
}

$fresh = $contracts->findByTenant($tenantId);

// Tell the lessor (notification bell). A failure here must not undo the acceptance.
try {
    $notify = new NotifyModel($pdo);
    $nid = $notify->logNotification(
        (int) $lessee['id'],
        'lessor',
        'Contract accepted',
        ($lessee['full_name'] ?? 'Your tenant') . ' accepted lease contract LC-' . str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT) . ' (version ' . $version . ').'
    );
    $notify->addRecipients($nid, [(int) $contract['lessor_id']]);
} catch (Throwable $e) {
    error_log('Contract accept notification failed: ' . $e->getMessage());
}

// Post acceptance confirmation to the message thread
try {
    $repo = new MessageRepository($pdo);
    $threadId = $repo->findOrCreateThread((int) $contract['lessor_id'], $tenantId);
    $acceptMsg = "✅ OFFICIAL NOTICE: LEASE CONTRACT ACCEPTED (Version {$version})\n\n"
        . ($lessee['full_name'] ?? 'Tenant') . " has officially reviewed and accepted the finalized lease contract LC-"
        . str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT) . " (Version {$version}).\n\n"
        . "Both parties are officially bound by the agreed terms and clauses recorded on this platform.";
    $repo->add($threadId, 'tenant', $acceptMsg);
} catch (Throwable $e) {
    error_log('Contract accept thread message failed: ' . $e->getMessage());
}
audit_log($pdo, (int) $lessee['id'], 'Tenant accepted lease contract v' . $version, 'tenant_id ' . $tenantId);

json_ok(['status' => 'accepted', 'accepted_at' => $fresh['accepted_at'] ?? date('Y-m-d H:i:s')]);
