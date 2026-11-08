<?php
/**
 * GET /api/contract_view.php?tenant_id=5   (tenant_id optional — newest lease if omitted)
 * Header: Authorization: Bearer <token>
 *
 * Response: { "ok": true, "has_lease": true, "contract": {
 *   tenant_id, tenant_name, lessor_name, property_name, property_address, property_type,
 *   rent, term_months, since,
 *   clauses: [ { label, text, key, origin }, ... ],      ← origin: template | edited | custom
 *
 *   // added for negotiated contracts (old app versions can ignore these):
 *   is_custom (bool), status ("standard" | "finalized" | "accepted"), version,
 *   can_accept (bool), start_date, end_date, security_deposit, advance_rent, due_day,
 *   special_provisions, template_name, finalized_at, accepted_at,
 *   revision_pending (bool — lessor is preparing a new version; this is the previous one),
 *   rent_legal_basis, legal_references: [ { law, use } ], fingerprint
 * } }
 *
 * The tenant never sees a lessor's draft: while a contract is being drafted or revised,
 * this returns the last finalized version, or the standard contract if there is none yet.
 * Returns has_lease:false if the tenant doesn't have an active lease yet.
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\TenantRepository;
use App\Models\TenantModel;
use App\Services\ContractService;

Request::requireMethod('GET');

$lessee   = require_lessee_auth($pdo);
$tenantId = Request::positiveInt($_GET['tenant_id'] ?? 0);

$found = (new TenantRepository($pdo))->contractForLessee((int) $lessee['id'], $tenantId);
$lease = $found ? (new TenantModel($pdo))->leaseDocument((int) $found['tenant_id']) : null;

if (!$lease) {
    json_ok(['has_lease' => false]);
}

$c = ContractService::effective($pdo, $lease, true);

$clauses = [];
foreach ($c['clauses'] as $item) {
    $clauses[] = [
        'label'  => $item['label'],
        'text'   => $item['text'],
        'key'    => $item['key'],
        'origin' => ContractService::origin($item),
        'legal_basis' => $item['ref'] ?? '',
    ];
}

json_ok([
    'has_lease' => true,
    'contract'  => [
        'tenant_id'          => (int) $lease['id'],
        'tenant_name'        => $lessee['full_name'],
        'lessor_name'        => $lease['lessor_name'],
        'property_name'      => $lease['property_name'],
        'property_address'   => $lease['property_address'],
        'property_type'      => $lease['property_type'],
        'rent'               => $c['monthly_rent'],
        'term_months'        => $c['term_months'],
        'since'              => $lease['created_at'],
        'clauses'            => $clauses,

        'is_custom'          => $c['source'] !== 'standard',
        'status'             => $c['status'],
        'version'            => $c['version'],
        'can_accept'         => $c['source'] === 'custom' && $c['status'] === ContractService::STATUS_FINALIZED,
        'can_reject'         => $c['source'] === 'custom' && $c['status'] === ContractService::STATUS_FINALIZED,
        'start_date'         => $c['start_date'],
        'end_date'           => ContractService::computedEndDate($c['start_date'], $c['term_months'], $c['end_date']),
        'security_deposit'   => $c['security_deposit'],
        'advance_rent'       => $c['advance_rent'],
        'due_day'            => $c['due_day'],
        'special_provisions' => $c['special_provisions'],
        'template_name'      => $c['template_name'],
        'finalized_at'       => $c['finalized_at'],
        'accepted_at'        => $c['accepted_at'],
        'rejected_at'        => $c['rejected_at'] ?? null,
        'rejection_reason'   => $c['rejection_reason'] ?? '',
        'revision_pending'   => $c['source'] === 'archived',
        'rent_legal_basis'   => ContractService::RENT_REFERENCE,
        'legal_references'   => ContractService::legalReferences(),
        'fingerprint'        => ContractService::documentFingerprint((int) $lease['id'], $c),
    ],
]);
