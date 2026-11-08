<?php
/**
 * GET /api/tickets_list.php?tenant_id=5   (tenant_id optional — newest lease if omitted)
 * Header: Authorization: Bearer <token>
 * Response: { "ok": true, "tickets": [ { id, category, priority, title, stage, contractor, photos, created_at }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Formatters\Formatter;
use Api\Http\Request;
use Api\Repositories\TicketRepository;
use Api\Services\TenantResolver;

Request::requireMethod('GET');

$lessee = require_lessee_auth($pdo, true);

$tenant = (new TenantResolver($pdo))->forBilling($lessee, Request::positiveInt($_GET['tenant_id'] ?? 0));
if (!$tenant) {
    json_ok(['tickets' => []]); // no active lease yet — nothing to show
}

$tickets = array_map(
    [Formatter::class, 'ticket'],
    (new TicketRepository($pdo))->listForTenant((int) $tenant['tenant_id'])
);

json_ok(['tickets' => $tickets]);
