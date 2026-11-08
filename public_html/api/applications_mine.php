<?php
/**
 * GET /api/applications_mine.php
 * Header: Authorization: Bearer <token>
 * Response: { "ok": true, "applications": [ { id, property_name, term_months, rent, status, submitted_at, valid_id_path, ... }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Repositories\ApplicationRepository;

Request::requireMethod('GET');

$lessee = require_lessee_auth($pdo);

$applications = array_map(
    [Formatter::class, 'application'],
    (new ApplicationRepository($pdo))->listForLessee((int) $lessee['id'])
);

json_ok(['applications' => $applications]);
