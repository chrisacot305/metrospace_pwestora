<?php
namespace App\Models;

/** Table: payments */
class PaymentModel extends Model
{
    /** Payment + tenant + lessee contact, only if it belongs to this lessor (approve step). */
    public function findForApproval(int $paymentId, int $lessorId): ?array
    {
        return $this->one(
            'SELECT pay.*, t.tenant_name, t.lessee_id, t.application_id, a.rent AS monthly_rent,
                    u.phone AS lessee_phone, u.full_name AS lessee_full_name
             FROM payments pay
             JOIN tenants t ON t.id = pay.tenant_id
             LEFT JOIN applications a ON a.id = t.application_id
             LEFT JOIN users u ON u.id = t.lessee_id
             WHERE pay.id = ? AND pay.lessor_id = ?',
            [$paymentId, $lessorId]
        );
    }

    public function findForLessor(int $paymentId, int $lessorId): ?array
    {
        return $this->one(
            'SELECT pay.*, t.tenant_name FROM payments pay
             JOIN tenants t ON t.id = pay.tenant_id
             WHERE pay.id = ? AND pay.lessor_id = ?',
            [$paymentId, $lessorId]
        );
    }

    public function markVerified(int $paymentId, int $lessorId, string $note): void
    {
        $this->run('UPDATE payments SET logged_by = ?, note = ? WHERE id = ?', [$lessorId, $note, $paymentId]);
    }

    public function markDeclined(int $paymentId, string $note): void
    {
        $this->run('UPDATE payments SET logged_by = NULL, note = ? WHERE id = ?', [$note, $paymentId]);
    }

    /** Offline payment typed in by the lessor — starts as pending (logged_by = NULL). */
    public function createPending(int $tenantId, int $lessorId, float $amount, string $method, string $note, string $paidAt): int
    {
        return $this->insert(
            'INSERT INTO payments (tenant_id, lessor_id, amount, method, note, paid_at, logged_by)
             VALUES (?, ?, ?, ?, ?, ?, NULL)',
            [$tenantId, $lessorId, $amount, $method, $note, $paidAt]
        );
    }

    /** Ledger: pending first, then newest. Optional search across tenant, property and note. */
    public function ledgerForLessor(int $lessorId, string $search = ''): array
    {
        $params = [$lessorId];
        $searchSql = '';
        if ($search !== '') {
            $searchSql = ' AND (t.tenant_name LIKE ? OR p.name LIKE ? OR pay.note LIKE ?) ';
            $wildcard = '%' . $search . '%';
            array_push($params, $wildcard, $wildcard, $wildcard);
        }
        return $this->all(
            "SELECT pay.*, t.tenant_name, p.name AS property_name
             FROM payments pay
             JOIN tenants t ON t.id = pay.tenant_id
             LEFT JOIN properties p ON p.id = t.property_id
             WHERE pay.lessor_id = ? $searchSql
             ORDER BY
               CASE WHEN (pay.logged_by IS NULL AND pay.note NOT LIKE '[DECLINED%') THEN 0 ELSE 1 END ASC,
               pay.paid_at DESC,
               pay.id DESC
             LIMIT 500",
            $params
        );
    }

    public function verifiedTotalThisMonth(int $lessorId): float
    {
        return (float) $this->value(
            "SELECT COALESCE(SUM(amount), 0) FROM payments
             WHERE lessor_id = ? AND logged_by IS NOT NULL
             AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
            [$lessorId]
        );
    }

    /** Sidebar badge: payments waiting for this lessor's verification. */
    public function pendingCountForLessor(int $lessorId): int
    {
        return (int) $this->value(
            "SELECT COUNT(*) FROM payments WHERE lessor_id = ? AND (logged_by IS NULL OR note LIKE '[PENDING]%') AND (note NOT LIKE '[DECLINED%')",
            [$lessorId]
        );
    }
}
