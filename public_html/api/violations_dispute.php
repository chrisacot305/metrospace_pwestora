<?php
/**
 * POST /api/violations_dispute.php
 * Header: Authorization: Bearer <token>
 * Body: { "violation_id": 41, "reason": "The noise was from Unit 3B beside us, not our shop." }
 * Response: { "ok": true, "message": "Dispute filed and forwarded to lessor messages thread." }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\MessageRepository;
use Api\Repositories\ViolationRepository;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$body = Request::json();
$violationId = Request::positiveInt($body['violation_id'] ?? 0);
$reason = trim((string) ($body['reason'] ?? ''));

if ($violationId <= 0) {
    json_error('Missing or invalid violation_id.');
}

if ($reason === '') {
    json_error('Please provide an explanation for your dispute.');
}

$violRepo = new ViolationRepository($pdo);
$viol = $violRepo->dispute($violationId, (int) $lessee['id'], $reason);

if (!$viol) {
    json_error('Violation not found or cannot be disputed.', 404);
}

// Forward dispute notice and explanation to the Messages thread between lessor and tenant
$msgRepo = new MessageRepository($pdo);
$threadId = $msgRepo->findOrCreateThread((int) $viol['lessor_id'], (int) $viol['tenant_id']);

$disputeMsg = "⚠️ CITATION DISPUTE FILED (Strike " . $viol['strike'] . " • " . $viol['category'] . ")\n\n"
    . "Tenant Explanation:\n\"" . $reason . "\"\n\n"
    . "This citation has been flagged for dispute review. You may discuss the circumstances in this conversation thread. If the explanation is accepted, you can revoke the strike from the Violations dashboard.";

$msgRepo->add($threadId, 'tenant', $disputeMsg, 'Dispute Notice');

json_ok([
    'message' => 'Dispute filed successfully and forwarded to your lessor messages thread.',
    'thread_id' => $threadId,
]);

