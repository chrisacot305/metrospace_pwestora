<?php
/**
 * GET /api/messages_thread.php?tenant_id=5   (tenant_id optional — newest lease if omitted)
 * Header: Authorization: Bearer <token>
 * Response: { "ok": true, "lessor_name": "...", "messages": [ { sender, notice_type, body, sent_at }, ... ] }
 * Messages are chronological (oldest first), same as the web chat view.
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\MessageRepository;
use Api\Services\TenantResolver;

Request::requireMethod('GET');

$lessee = require_lessee_auth($pdo);

$tenant = (new TenantResolver($pdo))->forLessee($lessee, Request::positiveInt($_GET['tenant_id'] ?? 0));
if (!$tenant) {
    json_ok(['lessor_name' => null, 'messages' => []]);
}

$repo     = new MessageRepository($pdo);
$threadId = $repo->findThreadId((int) $tenant['lessor_id'], (int) $tenant['tenant_id']);
$messages = $threadId ? $repo->listForThread($threadId) : [];

json_ok(['lessor_name' => $tenant['lessor_name'], 'messages' => $messages]);
