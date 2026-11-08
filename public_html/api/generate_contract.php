<?php
/**
 * POST /api/generate_contract.php
 * Header: Authorization: Bearer <token>   (now required — before, anyone could call this)
 * Body: { "lease_id": 12, "selected_terms": ["Term A", "Term B", ...] }
 * Response: { "success": true, "contract": "<h2>...</h2>", "status": "Contract Review" }
 *
 * Only the lessee who owns the lease request can generate its contract.
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Http\Response;
use Api\Repositories\LeaseRequestRepository;

const CONTRACT_STATUS = 'Contract Review';
const MAX_TERMS       = 50;
const MAX_TERM_LENGTH = 500;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$data   = Request::json();

$leaseId       = Request::positiveInt($data['lease_id'] ?? 0);
$selectedTerms = $data['selected_terms'] ?? [];

if ($leaseId <= 0) {
    Response::send(['success' => false, 'message' => 'Lease ID required']);
}
if (!is_array($selectedTerms) || count($selectedTerms) > MAX_TERMS) {
    Response::send(['success' => false, 'message' => 'selected_terms must be a list of up to ' . MAX_TERMS . ' items']);
}

$leaseRequests = new LeaseRequestRepository($pdo);
$details = $leaseRequests->findContractDetailsForLessee($leaseId, (int) $lessee['id']);

if (!$details) {
    Response::send(['success' => false, 'message' => 'Lease request not found']);
}

// Everything that goes into the HTML is escaped, so no one can inject scripts into the contract.
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$contractHtml  = '<h2>COMMERCIAL SPACE LEASE CONTRACT</h2>';
$contractHtml .= '<p><strong>LESSOR:</strong> ' . $e($details['lessor_name']) . '</p>';
$contractHtml .= '<p><strong>LESSEE:</strong> ' . $e($details['lessee_name']) . '</p>';
$contractHtml .= '<p><strong>PROPERTY:</strong> ' . $e($details['property_name']) . ' (' . $e($details['address']) . ')</p>';
$contractHtml .= '<p><strong>RENTAL AMOUNT:</strong> ₱' . number_format((float) $details['asking_rent']) . ' / month</p>';
$contractHtml .= '<hr><h3>AGREED LEASE TERMS & CONDITIONS</h3><ul>';
foreach ($selectedTerms as $term) {
    if (is_scalar($term)) {
        $contractHtml .= '<li>' . $e(mb_substr((string) $term, 0, MAX_TERM_LENGTH)) . '</li>';
    }
}
$contractHtml .= '</ul>';

$leaseRequests->saveContract($leaseId, $contractHtml, CONTRACT_STATUS);

Response::send([
    'success'  => true,
    'contract' => $contractHtml,
    'status'   => CONTRACT_STATUS,
]);
