<?php
/**
 * GET /api/payments_list.php
 * Header: Authorization: Bearer <token>
 * Response: { "ok": true, "payments": [ { id, amount, method, note, paid_at }, ... ] }
 */
require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Use GET.', 405);
}

$lessee = require_lessee_auth($pdo, true);

$lesseeName = $lessee['full_name'] ?? ($lessee['name'] ?? '');
$tenantIdParam = isset($_GET['tenant_id']) && (int)$_GET['tenant_id'] > 0 ? (int)$_GET['tenant_id'] : 0;

if ($tenantIdParam > 0) {
    $tenantStmt = $pdo->prepare(
        'SELECT t.id FROM tenants t
         LEFT JOIN applications a ON a.id = t.application_id
         WHERE (t.lessee_id = ? OR t.tenant_name = ? OR a.lessee_id = ?) AND t.id = ? LIMIT 1'
    );
    $tenantStmt->execute([$lessee['id'], $lesseeName, $lessee['id'], $tenantIdParam]);
    $tenantId = $tenantStmt->fetchColumn();
} else {
    $tenantStmt = $pdo->prepare(
        'SELECT t.id FROM tenants t
         LEFT JOIN applications a ON a.id = t.application_id
         WHERE t.lessee_id = ? OR t.tenant_name = ? OR a.lessee_id = ?
         ORDER BY t.created_at DESC LIMIT 1'
    );
    $tenantStmt->execute([$lessee['id'], $lesseeName, $lessee['id']]);
    $tenantId = $tenantStmt->fetchColumn();
}

if (!$tenantId) {
    $fallbackStmt = $pdo->query('SELECT id FROM tenants ORDER BY id DESC LIMIT 1');
    $tenantId = $fallbackStmt->fetchColumn();
}

if (!$tenantId) {
    json_ok(['payments' => []]);
}

$stmt = $pdo->prepare(
    'SELECT id, amount, method, note, paid_at, logged_by FROM payments WHERE tenant_id = ? ORDER BY paid_at DESC, id DESC'
);
$stmt->execute([$tenantId]);
$payments = $stmt->fetchAll();

foreach ($payments as &$p) {
    $p['id'] = (int) $p['id'];
    $p['amount'] = (float) $p['amount'];
    $noteStr = is_string($p['note'] ?? '') ? $p['note'] : '';
    $isPending = ($p['logged_by'] === null || str_contains(strtoupper($noteStr), '[PENDING]') || stripos($noteStr, 'pending verification') !== false);
    $isDeclined = (stripos($noteStr, '[DECLINED') !== false);
    $p['status'] = $isDeclined ? 'declined' : ($isPending ? 'pending' : 'approved');

    $refNo = '';
    $target = '';
    if (preg_match('/Ref:\s*([^|\r\n]+)/i', $p['note'] ?? '', $m)) {
        $refNo = trim($m[1]);
    }
    if (preg_match('/Target:\s*([^|\r\n]+)/i', $p['note'] ?? '', $m)) {
        $target = trim($m[1]);
    }
    $p['reference_no'] = $refNo;
    $p['target'] = $target;
}

json_ok(['payments' => $payments]);
