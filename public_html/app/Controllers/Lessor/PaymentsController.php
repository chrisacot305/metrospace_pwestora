<?php
namespace App\Controllers\Lessor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\DepositModel;
use App\Models\PaymentModel;
use App\Models\PropertyModel;
use App\Models\TenantModel;
use App\Services\RentBillingService;

/**
 * Lessor → Rent Payments (was modules/payments.php)
 * Verify online tenant transfers, decline them, or log offline payments.
 */
final class PaymentsController extends Controller
{
    private const METHODS = [
        'cash'          => 'Cash',
        'bank_transfer' => 'Bank Transfer / InstaPay',
        'gcash'         => 'GCash',
        'other'         => 'Other',
    ];

    public function handle(array $user): array
    {
        $lessorId = (int) $user['id'];
        $error = '';
        $msg = is_string($_GET['msg'] ?? null) ? $_GET['msg'] : '';
        $loggingPayment = isset($_GET['log']);
        $METHODS = self::METHODS;

        $payments = new PaymentModel($this->pdo);
        $tenantsModel = new TenantModel($this->pdo);
        $action = $_POST['action'] ?? '';

        // 1. APPROVE / CONFIRM PAYMENT
        if ($this->isPost() && $action === 'approve_payment') {
            Csrf::check();
            $paymentId = (int) ($_POST['payment_id'] ?? 0);
            try {
                $pRow = $payments->findForApproval($paymentId, $lessorId);
                if ($pRow) {
                    $tenantId    = (int) $pRow['tenant_id'];
                    $amount      = (float) $pRow['amount'];
                    $currentNote = (string) ($pRow['note'] ?? '');

                    $isTopUp = stripos($currentNote, 'Security Deposit Top-Up') !== false
                        || (stripos($currentNote, 'security_deposit') !== false
                            && stripos($currentNote, '[AUTO-DEPOSIT]') === false
                            && ($pRow['method'] ?? '') !== 'security_deposit');
                    $isPureRestructuring = stripos($currentNote, 'Target: Restructuring Plan') !== false
                        || (stripos($currentNote, 'Restructuring') !== false && stripos($currentNote, 'Both') === false);

                    $billingService = new RentBillingService($this->pdo);
                    $preBilling     = (!$isTopUp && !$isPureRestructuring)
                        ? $billingService->computeForTenantId($tenantId, false)
                        : null;

                    $newNote = str_contains($currentNote, '[PENDING]')
                        ? str_replace('[PENDING]', '[VERIFIED]', $currentNote)
                        : trim('[VERIFIED] ' . $currentNote);

                    $shouldSettleViolations = false;
                    if ($preBilling !== null) {
                        $prePenalty = (float) ($preBilling['penalty_amount'] ?? 0);
                        $preOverdue = (float) ($preBilling['overdue_rent_amount'] ?? 0);

                        if ($prePenalty > 0 && $amount >= ($preOverdue + $prePenalty - 0.01)) {
                            if (stripos($newNote, 'FineSettled:') === false) {
                                $newNote .= ' | FineSettled: ' . number_format($prePenalty, 2, '.', '');
                            }
                            $shouldSettleViolations = true;
                        } elseif ($prePenalty <= 0.009 && $preOverdue > 0 && $amount >= ($preOverdue - 0.01)) {
                            $shouldSettleViolations = true;
                        }
                    }

                    $payments->markVerified($paymentId, $lessorId, $newNote);

                    // Security Deposit Top-Up → credit the tenant's deposit buffer
                    if ($isTopUp) {
                        $tenantsModel->addToSecurityDeposit($tenantId, $amount);
                        (new DepositModel($this->pdo))->createLockedTopUp(
                            $lessorId, $tenantId, $amount,
                            $pRow['method'] ?? 'bank_transfer', 'Tenant Deposit Top-Up Verified'
                        );
                    } elseif ($shouldSettleViolations) {
                        $this->pdo->prepare(
                            'UPDATE violations SET is_paid = 1 WHERE tenant_id = ? AND (is_paid = 0 OR is_paid IS NULL)'
                        )->execute([$tenantId]);
                    }

                    if (!$isTopUp && !$isPureRestructuring) {
                        $postBilling = $billingService->computeForTenantId($tenantId, false);
                        if ($postBilling !== null && empty($postBilling['is_overdue']) && empty($postBilling['is_eviction']) && empty($postBilling['is_terminated'])) {
                            $this->pdo->prepare("UPDATE tenants SET status = 'active' WHERE id = ? AND status = 'overdue'")
                                ->execute([$tenantId]);
                        }
                    }

                    audit_log($this->pdo, $lessorId, 'Confirmed Tenant Payment',
                        '₱' . number_format($amount, 2) . ' from ' . $pRow['tenant_name']);

                    require_once APP_PATH . '/Services/RentNotificationService.php';
                    if (function_exists('send_payment_receipt_sms')) {
                        send_payment_receipt_sms($this->pdo, $paymentId);
                    }
                    $this->redirect('/dashboard.php?page=payments&msg=approved');
                }
                $error = 'Payment record not found or unauthorized.';
            } catch (\Throwable $e) {
                error_log('Approve payment error: ' . $e->getMessage());
                $error = 'Could not approve this payment. Please try again.';
            }
        }

        // 2. DECLINE PAYMENT
        if ($this->isPost() && $action === 'decline_payment') {
            Csrf::check();
            $paymentId = (int) ($_POST['payment_id'] ?? 0);
            $reason = trim((string) ($_POST['decline_reason'] ?? 'Declined by lessor'));
            try {
                $pRow = $payments->findForLessor($paymentId, $lessorId);
                if ($pRow) {
                    $currentNote = $pRow['note'] ?? '';
                    $tag = '[DECLINED: ' . htmlspecialchars($reason) . ']';
                    $newNote = str_contains($currentNote, '[PENDING]')
                        ? str_replace('[PENDING]', $tag, $currentNote)
                        : ($tag . ' ' . $currentNote);
                    $payments->markDeclined($paymentId, $newNote);

                    audit_log($this->pdo, $lessorId, 'Declined Tenant Payment',
                        '₱' . number_format((float) $pRow['amount'], 2) . ' from ' . $pRow['tenant_name'] . ' (' . $reason . ')');
                    $this->redirect('/dashboard.php?page=payments&msg=declined');
                }
                $error = 'Payment record not found or unauthorized.';
            } catch (\Throwable $e) {
                error_log('Decline payment error: ' . $e->getMessage());
                $error = 'Could not decline this payment. Please try again.';
            }
        }

        // 3. LOG MANUAL (offline) PAYMENT — starts as PENDING
        if ($this->isPost() && isset($_POST['log_payment'])) {
            Csrf::check();
            $tenantId  = (int) ($_POST['tenant_id'] ?? 0);
            $amount    = (float) str_replace(',', '', (string) ($_POST['amount'] ?? '0'));
            $rawMethod = is_string($_POST['method'] ?? null) ? $_POST['method'] : 'cash';
            $finalMethod = $rawMethod;
            $methodNote = '';
            if ($rawMethod === 'other') {
                $specified = trim((string) ($_POST['method_other'] ?? ''));
                $finalMethod = $specified !== '' ? mb_substr($specified, 0, 50) : 'Other';
                // FIX: payments.method is an ENUM (cash/bank_transfer/gcash/check/other). Saving "Maya"
                // there made the whole insert fail. Store 'other' and keep the typed name in the note.
                $methodNote = 'Method: ' . str_replace(['[', ']', '|'], ' ', $finalMethod) . ' | ';
            }
            $paidAt  = is_string($_POST['paid_at'] ?? null) ? $_POST['paid_at'] : '';
            $rawNote = trim((string) ($_POST['note'] ?? ''));
            $note    = mb_substr('[PENDING] ' . $methodNote . ($rawNote ? $rawNote : 'Offline manual entry'), 0, 255);

            $tRow = $tenantsModel->findOwned($tenantId, $lessorId);
            $validDate = \DateTime::createFromFormat('Y-m-d', $paidAt) !== false;

            if (!$tRow || $amount <= 0 || !isset(self::METHODS[$rawMethod]) || !$validDate) {
                $error = 'Please fill in every field with a valid tenant, amount, method, and date.';
            } else {
                try {
                    $payments->createPending($tenantId, $lessorId, $amount, $rawMethod, $note, $paidAt);
                    audit_log($this->pdo, $lessorId, 'Logged pending rent payment', "₱$amount via {$finalMethod} for {$tRow['tenant_name']}");
                    $this->redirect('/dashboard.php?page=payments&msg=logged');
                } catch (\Throwable $e) {
                    error_log('Log payment failed: ' . $e->getMessage());
                    $error = 'Could not log this payment — the error has been logged. Please try again.';
                }
            }
        }

        // DATA: unified ledger (pending on top)
        $q = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
        $allPayments = $payments->ledgerForLessor($lessorId, $q);

        $pendingCount = 0;
        $pendingTotal = 0;
        $historyCount = 0;
        foreach ($allPayments as $p) {
            $isPending = ($p['logged_by'] === null && !str_starts_with($p['note'] ?? '', '[DECLINED'));
            if ($isPending) {
                $pendingCount++;
                $pendingTotal += $p['amount'];
            } else {
                $historyCount++;
            }
        }
        unset($p);

        $monthTotal = $payments->verifiedTotalThisMonth($lessorId);
        $properties = (new PropertyModel($this->pdo))->listForLessor($lessorId);
        $tenants = $tenantsModel->listWithRent($lessorId);

        unset($payments, $tenantsModel, $action);
        return $this->view('lessor/payments', get_defined_vars());
    }
}
