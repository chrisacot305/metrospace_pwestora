<?php
namespace Api\Services;

use Api\Repositories\MessageRepository;
use Throwable;

/**
 * Posts payment receipts into the lessor–tenant chat thread.
 * A failed receipt never blocks the payment itself (it's only logged).
 */
final class ChatReceiptService
{
    public function __construct(private MessageRepository $messages)
    {
    }

    /** Receipt for a payment the tenant submitted (creates the thread if needed). */
    public function postSubmittedPayment(int $lessorId, int $tenantId, string $referenceNo, float $amount, string $targetLabel): void
    {
        $body = "🧾 DIGITAL PAYMENT RECEIPT\n\n"
              . "Payment Ref: {$referenceNo}\n"
              . 'Amount Submitted: ₱' . number_format($amount, 2) . "\n"
              . "Target: {$targetLabel}\n"
              . 'Date: ' . date('M d, Y h:i A') . "\n"
              . "Status: ⏳ Submitted for Verification\n\n"
              . 'Your payment has been received and submitted for lessor verification. Thank you!';

        try {
            $threadId = $this->messages->findOrCreateThread($lessorId, $tenantId);
            $this->messages->add($threadId, 'tenant', $body, 'Payment Receipt');
        } catch (Throwable $e) {
            error_log('Receipt message error: ' . $e->getMessage());
        }
    }

    /** Receipt for rent auto-covered by the security deposit (only if a thread already exists). */
    public function postDepositDeduction(int $lessorId, int $tenantId, string $refNo, float $amount, string $billingMonth): void
    {
        $body = "🧾 DIGITAL PAYMENT RECEIPT\n\n"
              . "Payment Ref: {$refNo}\n"
              . 'Amount Covered: ₱' . number_format($amount, 2) . "\n"
              . "Payment Method: Security Deposit (Escrow)\n"
              . 'Date: ' . date('M d, Y h:i A') . "\n"
              . "Status: ✅ Settled via Escrow\n\n"
              . "Your rent for {$billingMonth} was automatically covered using your Security Deposit advance shield.";

        try {
            $threadId = $this->messages->findThreadId($lessorId, $tenantId);
            if ($threadId) {
                // messages.sender only allows 'lessor' | 'tenant' in the database, so escrow receipts are posted on the lessor side.
                $this->messages->add($threadId, 'lessor', $body, 'Payment Receipt');
            }
        } catch (Throwable $e) {
            error_log('Deposit receipt message error: ' . $e->getMessage());
        }
    }
}
