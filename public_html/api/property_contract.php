<?php
/**
 * GET /api/property_contract.php?property_id=123
 *
 * Public/Lessee endpoint:
 * Returns the resolved contract template, terms, and checklist clauses
 * for a specific listing (custom building template -> lessor default -> Pwestora Standard).
 *
 * Response: {
 *   "ok": true,
 *   "property_id": 123,
 *   "contract_template": {
 *     "id": 0,
 *     "name": "Pwestora Standard",
 *     "description": "Standard commercial lease clauses compliant with Philippine law",
 *     "is_custom": false,
 *     "clauses": [
 *       { "key": "security_deposit", "label": "Security Deposit", "text": "...", "ref": "..." },
 *       ...
 *     ]
 *   }
 * }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\PropertyRepository;
use App\Services\ContractService;

Request::requireMethod('GET');

$propertyId = Request::positiveInt($_GET['property_id'] ?? $_GET['id'] ?? 0);
if ($propertyId <= 0) {
    json_error('Missing or invalid property_id.');
}

$property = (new PropertyRepository($pdo))->findVerifiedForApplication($propertyId);
if (!$property) {
    // If not found as verified, try general property lookup so lessors or previewers can inspect it
    $stmt = $pdo->prepare('SELECT id, lessor_id, name, asking_rent, lease_term, escalation FROM properties WHERE id = ?');
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();
}

if (!$property) {
    json_error('Property not found.', 404);
}

$lessorId = (int) ($property['lessor_id'] ?? 0);
$tpl = ContractService::resolveTemplateForProperty($pdo, $propertyId, $lessorId);

json_ok([
    'property_id'       => $propertyId,
    'property_name'     => $property['name'] ?? '',
    'asking_rent'       => isset($property['asking_rent']) ? (float) $property['asking_rent'] : null,
    'contract_template' => $tpl,
]);

