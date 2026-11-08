<?php
/**
 * POST /api/violations_acknowledge.php
 * Header: Authorization: Bearer <token>
 * Body: { "violation_id": 41 }
 * Response: { "ok": true }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\ViolationRepository;

Request::requireMethod('POST');

$lessee      = require_lessee_auth($pdo);
$violationId = Request::positiveInt(Request::json()['violation_id'] ?? 0);

if ($violationId <= 0 || !(new ViolationRepository($pdo))->acknowledge($violationId, (int) $lessee['id'])) {
    json_error('Violation not found.', 404);
}

json_ok([]);
