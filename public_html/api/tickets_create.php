<?php
/**
 * POST /api/tickets_create.php
 * Header: Authorization: Bearer <token>
 * Body: { "category": "plumbing", "priority": "high", "title": "...", "description": "..." }
 * Response: { "ok": true, "ticket_id": 12 }
 */
require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Use POST.', 405);
}

$lessee = require_lessee_auth($pdo);
$rawJson = json_body();
$body = array_merge(is_array($rawJson) ? $rawJson : [], $_POST, $_REQUEST);

$rawCategory = trim($body['category'] ?? '');
$category = strtolower($rawCategory);
if ($category === '') {
    $category = 'general';
}
if ($category === 'cleanliness') {
    $category = 'cleaning';
}

$rawPriority = strtolower(trim($body['priority'] ?? 'medium'));
$priority = in_array($rawPriority, ['low', 'medium', 'high', 'urgent'], true) ? $rawPriority : 'medium';

$title = trim($body['title'] ?? '');
if ($title === '') {
    $title = '[' . ucfirst($category) . '] Maintenance Issue';
}

$reqTenantId = isset($body['tenant_id']) && (int)$body['tenant_id'] > 0 ? (int)$body['tenant_id'] : 0;

if ($reqTenantId > 0) {
    $tenantStmt = $pdo->prepare('SELECT id, lessor_id FROM tenants WHERE lessee_id = ? AND id = ? LIMIT 1');
    $tenantStmt->execute([$lessee['id'], $reqTenantId]);
} else {
    $tenantStmt = $pdo->prepare('SELECT id, lessor_id FROM tenants WHERE lessee_id = ? ORDER BY created_at DESC LIMIT 1');
    $tenantStmt->execute([$lessee['id']]);
}
$tenant = $tenantStmt->fetch();

if (!$tenant) {
    json_error('You need an active lease before reporting a maintenance issue.', 403);
}

// Ensure photos column exists in maintenance_tickets table
try {
    $pdo->query("ALTER TABLE maintenance_tickets ADD COLUMN photos TEXT NULL AFTER contractor");
} catch (Throwable $e) {
    // Column already exists or error ignored
}

$insert = $pdo->prepare(
    'INSERT INTO maintenance_tickets (lessor_id, tenant_id, category, priority, title) VALUES (?, ?, ?, ?, ?)'
);
$insert->execute([$tenant['lessor_id'], $tenant['id'], $category, $priority, $title]);
$ticketId = (int) $pdo->lastInsertId();

// Handle photo uploads
$savedPhotos = [];
$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$dir = __DIR__ . "/../uploads/maintenance/{$ticketId}";

if (!empty($_FILES)) {
    foreach ($_FILES as $key => $fileInfo) {
        if (is_array($fileInfo['name'])) {
            for ($i = 0; $i < count($fileInfo['name']); $i++) {
                if ($fileInfo['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($fileInfo['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowedExt, true)) {
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $filename = 'photo_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        if (move_uploaded_file($fileInfo['tmp_name'][$i], "$dir/$filename")) {
                            $savedPhotos[] = "uploads/maintenance/{$ticketId}/$filename";
                        }
                    }
                }
            }
        } else {
            if ($fileInfo['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExt, true)) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $filename = 'photo_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    if (move_uploaded_file($fileInfo['tmp_name'], "$dir/$filename")) {
                        $savedPhotos[] = "uploads/maintenance/{$ticketId}/$filename";
                    }
                }
            }
        }
    }
}

if (!empty($savedPhotos)) {
    $upd = $pdo->prepare('UPDATE maintenance_tickets SET photos = ? WHERE id = ?');
    $upd->execute([json_encode($savedPhotos), $ticketId]);
}

json_ok([
    'ticket_id' => $ticketId,
    'photos'    => $savedPhotos,
], 201);
