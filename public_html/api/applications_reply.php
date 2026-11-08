<?php
/**
 * POST /api/applications_reply.php
 * Header: Authorization: Bearer <token>
 * Body: { "application_id": 8, "message": "I agree to the terms..." }
 * Response: { "ok": true, "messages": [ ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\ApplicationRepository;

const REPLY_MAX_LENGTH = 2000;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$body   = Request::json();

$applicationId = Request::positiveInt($body['application_id'] ?? 0);
$message       = Request::string($body, 'message', REPLY_MAX_LENGTH);

if ($applicationId <= 0 || $message === '') {
    json_error('application_id and message are required.');
}

$applications = new ApplicationRepository($pdo);
$app = $applications->findForLessee($applicationId, (int) $lessee['id']);

if (!$app) {
    json_error('Application not found or unauthorized.', 404);
}
if ($app['status'] !== 'pending') {
    json_error('This application is already ' . $app['status'] . ' and cannot be negotiated further.', 400);
}

$negotiation = [];
if (!empty($app['checklist_negotiation'])) {
    $decoded = json_decode($app['checklist_negotiation'], true);
    if (is_array($decoded)) {
        $negotiation = $decoded;
    }
}
if (!isset($negotiation['messages']) || !is_array($negotiation['messages'])) {
    $negotiation['messages'] = [];
}

// Append new lessee message
$negotiation['messages'][] = [
    'sender'      => 'lessee',
    'sender_name' => $lessee['full_name'] ?? 'Applicant',
    'message'     => $message,
    'sent_at'     => date('Y-m-d H:i:s'),
];

$applications->updateNegotiation($applicationId, json_encode($negotiation));

json_ok(['ok' => true, 'messages' => $negotiation['messages']]);
