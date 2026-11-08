<?php
/**
 * POST /api/messages_send.php
 * Header: Authorization: Bearer <token>
 * Body: { "body": "...", "tenant_id": 5 }   (tenant_id optional — newest lease if omitted)
 * Response: { "ok": true }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\MessageRepository;
use Api\Services\TenantResolver;

const MESSAGE_MAX_LENGTH = 5000;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$body   = Request::json();
$text   = Request::string($body, 'body', MESSAGE_MAX_LENGTH);

if ($text === '') {
    json_error('Message cannot be empty.');
}

$tenant = (new TenantResolver($pdo))->forLessee($lessee, Request::positiveInt($body['tenant_id'] ?? 0));
if (!$tenant) {
    json_error('You need an active lease before messaging your lessor.', 403);
}

$messages = new MessageRepository($pdo);
$threadId = $messages->findOrCreateThread((int) $tenant['lessor_id'], (int) $tenant['tenant_id']);
$messages->add($threadId, 'tenant', $text);

json_ok([]);
