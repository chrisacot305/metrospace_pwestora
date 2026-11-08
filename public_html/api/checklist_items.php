<?php
/**
 * GET /api/checklist_items.php
 * No auth required — shown before/during registration.
 * Response: { "ok": true, "items": [ { key, label, text }, ... ] }
 */
require __DIR__ . '/../app/init_api.php';
$LEASE_CHECKLIST_ITEMS = App\Services\LeaseChecklist::items();

use Api\Http\Request;

Request::requireMethod('GET');

$items = [];
foreach ($LEASE_CHECKLIST_ITEMS as $key => $item) {
    $items[] = ['key' => $key, 'label' => $item['label'], 'text' => $item['text']];
}

json_ok(['items' => $items]);
