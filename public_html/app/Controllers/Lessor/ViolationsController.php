<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\MessageModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;
use App\Models\ViolationModel;
use App\Services\ContractService;

/**
 * Lessor → Violations (was modules/violations.php)
 * Strike level auto-escalates per tenant (previous highest strike + 1).
 *
 * Violation categories are NOT a fixed list anymore: each tenant's categories are the clauses
 * of the contract THAT tenant agreed to (plus the rent obligation and special provisions).
 * They are read fresh on every page load, so when a revised contract is finalized the
 * categories change automatically. The violated clause is saved with the violation.
 */
final class ViolationsController extends Controller
{
    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $msg = is_string($_GET['msg'] ?? null) ? $_GET['msg'] : '';
        $issuing = isset($_GET['issue']);

        $violationsModel = new ViolationModel($this->pdo);
        $tenantsModel = new TenantModel($this->pdo);

        if ($this->isPost() && isset($_POST['issue_violation'])) {
            Csrf::check();
            $tenantId  = (int) ($_POST['tenant_id'] ?? 0);
            $clauseKey = is_string($_POST['clause_key'] ?? null) ? $_POST['clause_key'] : '';

            // The chosen clause must exist in THIS tenant's current contract.
            $chosen = null;
            $contractInfo = null;
            $lease = $tenantsModel->findOwned($tenantId, $lessorId) ? $tenantsModel->leaseDocument($tenantId) : null;
            if ($lease) {
                $cats = ContractService::violationCategories($this->pdo, $lease);
                $contractInfo = $cats['contract'];
                foreach ($cats['categories'] as $cat) {
                    if ($cat['key'] === $clauseKey) { $chosen = $cat; break; }
                }
            }

            if (!$lease || !$chosen) {
                $error = 'Please select a tenant and a clause from their contract.';
            } else {
                $category = $chosen['label'];
                try {
                    $newStrike = $violationsModel->highestStrike($tenantId) + 1;
                    $fineAmount = $newStrike >= 3 ? 1000.00 : 0.00;
                    $violationsModel->create($lessorId, $tenantId, $category, $newStrike, $fineAmount, [
                        'key'          => $chosen['key'],
                        'contract_ref' => $contractInfo['ref'] . ' · ' . $chosen['number'],
                        'text'         => $chosen['text'],
                        'ref'          => $chosen['ref'],
                    ]);

                    $messages = new MessageModel($this->pdo);
                    $threadId = $messages->findOrCreateThread($lessorId, $tenantId);

                    $strikeBadge = "STRIKE $newStrike OF 3";
                    $penaltyLine = $newStrike == 2
                        ? "\nPenalty Injected: ₱0.00 (Strike 2 recorded. Action required to prevent escalation to Strike 3 eviction)."
                        : ($newStrike >= 3
                            ? "\nPenalty Injected: ₱1,000.00 penalty & Formal Lease Termination / Eviction proceedings initiated."
                            : "\nAction Required: Immediate compliance with commercial property guidelines.");
                    $clauseLine = "\nContract provision: {$chosen['number']} ({$category}) of {$contractInfo['ref']}"
                                . "\n\"" . mb_strimwidth($chosen['text'], 0, 400, '...') . "\""
                                . ($chosen['ref'] !== '' ? "\nLegal basis: {$chosen['ref']}" : '');
                    $msgBody = "⚠️ OFFICIAL NOTICE OF INFRACTION ($strikeBadge)\nCategory: $category$clauseLine$penaltyLine\n\nA formal citation has been officially recorded against your lease agreement. Please review the citation details and disciplinary policy under your account.";
                    $messages->addFromLessor($threadId, $msgBody, 'Infraction Notice');

                    // Bell notification
                    $lesseeId = (int) ($lease['lessee_id'] ?? 0);
                    if ($lesseeId > 0) {
                        try {
                            $notify = new \App\Models\NotifyModel($this->pdo);
                            $nid = $notify->logNotification(
                                $lessorId,
                                'tenant',
                                'Notice of Infraction',
                                "A Strike {$newStrike} citation was recorded under your lease ({$category})."
                            );
                            $notify->addRecipients($nid, [$lesseeId]);
                        } catch (\Throwable $e) {
                            error_log('Violation bell notification failed: ' . $e->getMessage());
                        }
                    }

                    // SMS Notification
                    $phone = $tenantsModel->lesseePhone($tenantId);
                    if (!empty($phone)) {
                        try {
                            if (!function_exists('send_sms')) {
                                @require_once __DIR__ . '/../../Services/Sms.php';
                            }
                            if (function_exists('send_sms')) {
                                send_sms($phone, "[Pwestora] Official Notice: A Strike {$newStrike} citation ({$category}) was recorded for your lease. Please view details in your Pwestora app.");
                            }
                        } catch (\Throwable $e) {
                            error_log('Violation SMS dispatch failed: ' . $e->getMessage());
                        }
                    }

                    audit_log($this->pdo, $lessorId, 'Issued violation', "$category ({$contractInfo['ref']} · {$chosen['number']}) — strike $newStrike");

                    $msg = 'issued';
                    $issuing = false;
                    unset($_GET['issue']);
                    echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=violations&msg=issued");}</script>';
                } catch (\Throwable $e) {
                    error_log('Issue violation failed: ' . $e->getMessage());
                    $error = 'Could not issue this violation: ' . $e->getMessage();
                }
            }
        }

        if ($this->isPost() && isset($_POST['revoke_violation'])) {
            Csrf::check();
            $violationId = (int) ($_POST['violation_id'] ?? 0);
            $revocationReason = trim((string) ($_POST['revocation_reason'] ?? 'Dispute accepted by lessor'));

            if ($violationId <= 0) {
                $error = 'Invalid citation selected.';
            } else {
                try {
                    $vRow = $violationsModel->findOwned($violationId, $lessorId);
                    if (!$vRow) {
                        $error = 'Citation not found or does not belong to your account.';
                    } else {
                        $violationsModel->revoke($violationId, $lessorId, $revocationReason);
                        $tenantId = (int) $vRow['tenant_id'];
                        $cat = $vRow['category'];
                        $stk = $vRow['strike'];

                        $messages = new MessageModel($this->pdo);
                        $threadId = $messages->findOrCreateThread($lessorId, $tenantId);
                        $revMsg = "🟢 CITATION REVOKED: Lessor has reviewed and revoked Citation #{$violationId} (Strike {$stk} · {$cat}).\n\nExplanation / Note: {$revocationReason}\n\nThis strike has been dismissed and cleared from your active compliance record.";
                        $messages->addFromLessor($threadId, $revMsg, 'Dispute Resolution');

                        audit_log($this->pdo, $lessorId, 'Revoked violation', "Citation #{$violationId} revoked");
                        $msg = 'revoked';
                        echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=violations&msg=revoked");}</script>';
                    }
                } catch (\Throwable $e) {
                    error_log('Revoke violation failed: ' . $e->getMessage());
                    $error = 'Could not revoke citation: ' . $e->getMessage();
                }
            }
        }

        if ($this->isPost() && isset($_POST['reject_dispute'])) {
            Csrf::check();
            $violationId = (int) ($_POST['violation_id'] ?? 0);
            $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? 'Dispute explanation reviewed and rejected by lessor.'));

            if ($violationId <= 0) {
                $error = 'Invalid citation selected.';
            } else {
                try {
                    $vRow = $violationsModel->findOwned($violationId, $lessorId);
                    if (!$vRow) {
                        $error = 'Citation not found or does not belong to your account.';
                    } else {
                        $violationsModel->rejectDispute($violationId, $lessorId, $rejectionReason);
                        $tenantId = (int) $vRow['tenant_id'];
                        $cat = $vRow['category'];
                        $stk = $vRow['strike'];

                        $messages = new MessageModel($this->pdo);
                        $threadId = $messages->findOrCreateThread($lessorId, $tenantId);
                        $rejMsg = "🔴 DISPUTE REJECTED: Lessor has reviewed your explanation for Citation #{$violationId} (Strike {$stk} · {$cat}) and upheld the violation.\n\nDecision / Reason: {$rejectionReason}\n\nStrike {$stk} is now confirmed active on your lease compliance record.";
                        $messages->addFromLessor($threadId, $rejMsg, 'Dispute Resolution');

                        audit_log($this->pdo, $lessorId, 'Rejected violation dispute', "Citation #{$violationId} dispute rejected; strike upheld");
                        $msg = 'dispute_rejected';
                        echo '<script>if(window.history.replaceState){window.history.replaceState(null,null,"/dashboard.php?page=violations&msg=dispute_rejected");}</script>';
                    }
                } catch (\Throwable $e) {
                    error_log('Reject dispute failed: ' . $e->getMessage());
                    $error = 'Could not reject dispute: ' . $e->getMessage();
                }
            }
        }

        $violations = $violationsModel->listForLessor($lessorId);
        $properties = (new PropertyModel($this->pdo))->listForLessor($lessorId);
        $tenants    = $tenantsModel->listForLessor($lessorId);

        // tenant_id => contract-based categories (sent to the page as JSON for the Issue form).
        $tenantCategories = [];
        foreach ($tenants as $t) {
            $lease = $tenantsModel->leaseDocument((int) $t['id']);
            if ($lease) {
                $tenantCategories[(int) $t['id']] = ContractService::violationCategories($this->pdo, $lease);
            }
        }

        $selectedTenantId = (int) ($_POST['tenant_id'] ?? 0);
        $selectedClause   = is_string($_POST['clause_key'] ?? null) ? $_POST['clause_key'] : '';

        unset($violationsModel, $tenantsModel, $messages);
        return $this->view('lessor/violations', get_defined_vars());
    }
}
