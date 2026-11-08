<?php
/**
 * POST /api/contract_reject.php
 * Header: Authorization: Bearer <token>
 * Body: { "tenant_id": 5, "version": 2, "reason": "Monthly escalation clause too high..." }
 * Response: { "ok": true, "status": "rejected", "rejected_at": "2026-11-08 20:10:00", "version": 2 }
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\MessageRepository;
use Api\Repositories\TenantRepository;
use App\Models\NotifyModel;
use App\Models\TenantModel;

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo);
$body   = Request::json();

$tenantId = Request::positiveInt($body['tenant_id'] ?? 0);
$version  = Request::positiveInt($body['version'] ?? 0);
$reason   = trim((string) ($body['reason'] ?? ''));

if ($tenantId <= 0 || $version <= 0) {
    json_error('tenant_id and version are required.');
}
if (mb_strlen($reason) < 3) {
    json_error('Please provide a reason or note explaining what needs to be changed.');
}

// 1. Verify that the lease belongs to this authenticated lessee
$tenancy = (new TenantRepository($pdo))->findForLessee((int) $lessee['id'], $tenantId);
if (!$tenancy) {
    json_error('Lease not found or access denied.', 404);
}

// 2. Fetch the current contract row directly
$stmt = $pdo->prepare('SELECT * FROM lease_contracts WHERE tenant_id = ? LIMIT 1');
$stmt->execute([$tenantId]);
$contract = $stmt->fetch();

if (!$contract || $contract['status'] === 'draft') {
    json_error('There is no finalized contract awaiting acceptance.', 409);
}
if ($contract['status'] === 'accepted') {
    json_error('This contract version has already been accepted and cannot be declined.', 409);
}
if ($contract['status'] === 'rejected') {
    json_ok([
        'status'           => 'rejected',
        'rejected_at'      => $contract['rejected_at'] ?? date('Y-m-d H:i:s'),
        'rejection_reason' => $contract['rejection_reason'] ?? $reason,
        'version'          => $version,
        'already'          => true,
    ]);
}
if ((int) $contract['version'] !== $version) {
    json_error('The lessor has updated this contract (now v' . (int) $contract['version'] . '). Please reload it to review the latest terms.', 409);
}

// 3. Update contract status to 'rejected' with self-healing column migration
$now = date('Y-m-d H:i:s');
try {
    $upd = $pdo->prepare(
        "UPDATE lease_contracts 
         SET status = 'rejected', rejected_at = NOW(), rejection_reason = ?
         WHERE tenant_id = ? AND version = ? AND status = 'finalized'"
    );
    $upd->execute([$reason, $tenantId, $version]);
} catch (\PDOException $e) {
    if (str_contains($e->getMessage(), 'Unknown column')) {
        try { $pdo->exec("ALTER TABLE lease_contracts ADD COLUMN rejected_at DATETIME NULL"); } catch (\Throwable $ig) {}
        try { $pdo->exec("ALTER TABLE lease_contracts ADD COLUMN rejection_reason TEXT NULL"); } catch (\Throwable $ig) {}
        $upd = $pdo->prepare(
            "UPDATE lease_contracts 
             SET status = 'rejected', rejected_at = NOW(), rejection_reason = ?
             WHERE tenant_id = ? AND version = ? AND status = 'finalized'"
        );
        $upd->execute([$reason, $tenantId, $version]);
    } else {
        error_log('Contract reject SQL error: ' . $e->getMessage());
        json_error('Could not update contract status: ' . $e->getMessage(), 500);
    }
}

$tenants = new TenantModel($pdo);
$lease   = $tenants->leaseDocument($tenantId);
$propName = $lease['property_name'] ?? 'Commercial Space';
$lessorId = (int) $contract['lessor_id'];

// 4. Post rejection notice into the in-app message thread
try {
    $repo = new MessageRepository($pdo);
    $threadId = $repo->findOrCreateThread($lessorId, $tenantId);
    $rejectMsg = "⚠️ OFFICIAL NOTICE: CONTRACT REVISION REQUESTED (Version {$version})\n\n"
        . ($lessee['full_name'] ?? 'Tenant') . " has reviewed the lease agreement for {$propName} and declined acceptance.\n\n"
        . "Tenant Feedback / Requested Revisions:\n"
        . "\"{$reason}\"\n\n"
        . "Action Required: Please open your Lessor Dashboard under Contracts, review the feedback, and click 'Revise Contract' to update the terms.";
    try {
        $repo->add($threadId, 'tenant', $rejectMsg, 'Contract Notice');
    } catch (\Throwable $argErr) {
        $repo->add($threadId, 'tenant', $rejectMsg);
    }
} catch (\Throwable $e) {
    error_log('Contract reject thread message failed: ' . $e->getMessage());
}

// 5. Bell notification for the lessor
try {
    $notify = new NotifyModel($pdo);
    $nid = $notify->logNotification(
        (int) $lessee['id'],
        'lessor',
        'Contract Revision Requested',
        ($lessee['full_name'] ?? 'Your tenant') . " declined Lease Agreement v{$version} for {$propName}. Reason: \"{$reason}\""
    );
    $notify->addRecipients($nid, [$lessorId]);
} catch (\Throwable $e) {
    error_log('Contract reject notification failed: ' . $e->getMessage());
}

// 6. SMS notification to lessor if phone number exists
try {
    $userStmt = $pdo->prepare('SELECT phone, full_name FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$lessorId]);
    $lessorUser = $userStmt->fetch();

    if ($lessorUser && !empty($lessorUser['phone'])) {
        if (!function_exists('send_sms')) {
            @require_once __DIR__ . '/../app/Services/Sms.php';
        }
        if (function_exists('send_sms')) {
            $smsReason = mb_strimwidth($reason, 0, 80, '...');
            send_sms(
                $lessorUser['phone'],
                "[Pwestora] Tenant {$lessee['full_name']} declined lease agreement v{$version} for {$propName}. Reason: {$smsReason}. Please check your dashboard to revise."
            );
        }
    }
} catch (\Throwable $e) {
    error_log('Contract reject SMS failed: ' . $e->getMessage());
}

audit_log($pdo, (int) $lessee['id'], 'Tenant declined lease contract v' . $version, 'tenant_id ' . $tenantId);

json_ok([
    'status'           => 'rejected',
    'rejected_at'      => $now,
    'rejection_reason' => $reason,
    'version'          => $version,
]);
