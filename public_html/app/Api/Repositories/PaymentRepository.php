<?php
namespace Api\Repositories;

/** Table: payments */
final class PaymentRepository extends Repository
{
    public function listForTenant(int $tenantId): array
    {
        return $this->fetchAll(
            'SELECT id, amount, method, note, paid_at, logged_by
             FROM payments WHERE tenant_id = ?
             ORDER BY paid_at DESC, id DESC',
            [$tenantId]
        );
    }

    /** Payment sent by the tenant, waiting for the lessor to verify it (logged_by = NULL). */
    public function createPending(int $tenantId, int $lessorId, float $amount, string $method, string $note): int
    {
        return $this->insert(
            'INSERT INTO payments (tenant_id, lessor_id, amount, method, note, paid_at, logged_by)
             VALUES (?, ?, ?, ?, ?, CURDATE(), NULL)',
            [$tenantId, $lessorId, $amount, $method, $note]
        );
    }

    /** Payment recorded automatically by the system on the lessor's behalf. */
    public function createFromSecurityDeposit(int $tenantId, int $lessorId, float $amount, string $note): int
    {
        return $this->insert(
            "INSERT INTO payments (tenant_id, lessor_id, amount, method, note, paid_at, logged_by)
             VALUES (?, ?, ?, 'security_deposit', ?, CURDATE(), ?)",
            [$tenantId, $lessorId, $amount, $note, $lessorId]
        );
    }

    /** Verified monthly-rent payments for one calendar month. */
    public function verifiedRentPaidInMonth(int $tenantId, int $month, int $year): float
    {
        return (float) $this->fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE tenant_id = ? AND (note LIKE '%[VERIFIED]%' OR logged_by IS NOT NULL)
               AND (note LIKE '%Monthly Rent%' OR note NOT LIKE '%Restructuring%')
               AND MONTH(paid_at) = ? AND YEAR(paid_at) = ?",
            [$tenantId, $month, $year]
        );
    }
}
