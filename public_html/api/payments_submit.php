<?php
/**
 * POST /api/payments_submit.php
 * Header: Authorization: Bearer <token>
 * Body: {
 *   "amount": 1250.00,
 *   "method": "bank_transfer",
 *   "target": "monthly_rent" | "restructuring_plan" | "both" | "security_deposit" | "overdue_october" | "full_balance",
 *   "reference_no": "1029384756",
 *   "note": "...",
 *   "tenant_id": 5          (optional — newest lease if omitted)
 * }
 * Submits rent payment with proof reference for lessor verification.
 */
require __DIR__ . '/../app/init_api.php';

use Api\Http\Request;
use Api\Repositories\MessageRepository;
use Api\Repositories\PaymentRepository;
use Api\Services\ChatReceiptService;
use Api\Services\TenantResolver;
use Api\Support\Sanitizer;

const PAYMENT_MAX_AMOUNT = 10000000; // ₱10M sanity limit

const PAYMENT_TARGETS = [
    'monthly_rent'       => 'Monthly Rent',
    'security_deposit'   => 'Security Deposit Top-Up',
    'restructuring_plan' => 'Restructuring Plan',
    'both'               => 'Both (Rent + Restructuring)',
    'overdue_cycle'      => 'Overdue Rent + Strike Penalty',
    'overdue_october'    => 'Overdue Rent + Strike Penalty',
    'full_balance'       => 'Full Balance (Overdue + Advance)',
];

Request::requireMethod('POST');

$lessee = require_lessee_auth($pdo, true);
$body   = Request::json();

$amount = round(Request::positiveFloat($body['amount'] ?? 0), 2);
$target = Request::string($body, 'target', 40, 'monthly_rent');
// Strip "[", "]" and "|" so a tenant can't fake "[VERIFIED]" status tags in the note.
$referenceNo = Sanitizer::noteFragment(Request::string($body, 'reference_no', 200), 100);
$noteExtra   = Sanitizer::noteFragment(Request::string($body, 'note', 500), 300);

if ($amount <= 0) {
    json_error('Please specify a valid payment amount greater than zero.');
}
if ($amount > PAYMENT_MAX_AMOUNT) {
    json_error('Payment amount is too large.');
}
if ($referenceNo === '') {
    json_error('A transaction reference number or receipt details are required.');
}

$tenant = (new TenantResolver($pdo))->forBilling($lessee, Request::positiveInt($body['tenant_id'] ?? 0));
if (!$tenant) {
    json_error('No active lease found for this account.', 403);
}

$targetLabel = PAYMENT_TARGETS[$target] ?? PAYMENT_TARGETS['monthly_rent'];
$note = "[PENDING] Target: $targetLabel | Ref: $referenceNo" . ($noteExtra !== '' ? " | $noteExtra" : '');

$tenantId = (int) $tenant['tenant_id'];
$lessorId = (int) $tenant['lessor_id'];

$paymentId = (new PaymentRepository($pdo))->createPending($tenantId, $lessorId, $amount, 'bank_transfer', $note);

// Post official digital payment receipt into in-app chat thread
(new ChatReceiptService(new MessageRepository($pdo)))
    ->postSubmittedPayment($lessorId, $tenantId, $referenceNo, $amount, $targetLabel);

audit_log($pdo, $lessee['id'], 'Tenant Submitted Payment', "₱$amount for $targetLabel (Ref: $referenceNo)");

json_ok([
    'payment_id'   => $paymentId,
    'status'       => 'pending',
    'amount'       => $amount,
    'target'       => $target,
    'reference_no' => $referenceNo,
    'message'      => 'Payment submitted for lessor verification.',
], 201);
