<?php
namespace Api\Repositories;

/** Table: violations */
final class ViolationRepository extends Repository
{
    private const LIST_SELECT =
        'SELECT v.id, v.tenant_id, v.lessor_id, u.company_name AS lessor_name,
                v.category, v.strike, v.description,
                v.fine_amount,
                COALESCE(v.is_paid, 0) AS is_paid,
                v.acknowledged, v.issued_at,
                v.contract_ref, v.clause_text, v.clause_ref,
                COALESCE(v.disputed, 0) AS disputed,
                v.dispute_reason, v.disputed_at,
                COALESCE(v.dispute_status, "") AS dispute_status,
                v.dispute_resolved_at, v.dispute_resolution_note,
                COALESCE(v.is_revoked, 0) AS is_revoked,
                v.revoked_at, v.revocation_reason
         FROM violations v
         JOIN users u ON u.id = v.lessor_id';

    private function ensureColumns(): void
    {
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;

        $cols = [
            'fine_amount'             => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'is_paid'                 => 'TINYINT(1) NOT NULL DEFAULT 0',
            'clause_key'              => 'VARCHAR(80) NULL',
            'contract_ref'            => 'VARCHAR(60) NULL',
            'clause_text'             => 'TEXT NULL',
            'clause_ref'              => 'VARCHAR(300) NULL',
            'disputed'                => 'TINYINT(1) NOT NULL DEFAULT 0',
            'dispute_reason'          => 'TEXT NULL',
            'disputed_at'             => 'DATETIME NULL',
            'dispute_status'          => 'VARCHAR(30) NULL DEFAULT NULL',
            'dispute_resolved_at'     => 'DATETIME NULL',
            'dispute_resolution_note' => 'TEXT NULL',
            'is_revoked'              => 'TINYINT(1) NOT NULL DEFAULT 0',
            'revoked_at'              => 'DATETIME NULL',
            'revocation_reason'       => 'TEXT NULL',
        ];

        foreach ($cols as $col => $def) {
            try {
                $this->pdo->exec("ALTER TABLE `violations` ADD COLUMN `$col` $def");
            } catch (\Throwable $e) {}
        }
    }

    public function listForTenant(int $tenantId): array
    {
        $this->ensureColumns();
        return $this->fetchAll(self::LIST_SELECT . ' WHERE v.tenant_id = ? ORDER BY v.issued_at DESC', [$tenantId]);
    }

    public function listForLessee(int $lesseeId): array
    {
        $this->ensureColumns();
        return $this->fetchAll(
            self::LIST_SELECT . ' JOIN tenants t ON t.id = v.tenant_id WHERE t.lessee_id = ? ORDER BY v.issued_at DESC',
            [$lesseeId]
        );
    }

    /**
     * Unpaid strike 2+ violations with their fine.
     * Respects recorded fine_amount (late rent = 500, conduct = 0; strike 3 = 1000).
     */
    public function unpaidFinesForTenant(int $tenantId): array
    {
        return $this->fetchAll(
            'SELECT id, category, strike,
                    CASE
                        WHEN fine_amount >= 1500.00 THEN 1000.00
                        WHEN fine_amount IS NOT NULL THEN fine_amount
                        WHEN strike >= 3 THEN 1000.00
                        WHEN strike = 2 AND (category LIKE "%Rent%" OR category LIKE "%Due%") THEN 500.00
                        ELSE 0.00
                    END AS fine_amount,
                    issued_at
             FROM violations
             WHERE tenant_id = ?
               AND (is_revoked = 0 OR is_revoked IS NULL)
               AND (disputed = 0 OR disputed IS NULL OR dispute_status = "rejected")
               AND (is_paid = 0 OR is_paid IS NULL)
               AND strike >= 2
             ORDER BY strike DESC, id DESC',
            [$tenantId]
        );
    }

    /** Returns false if the violation doesn't exist or isn't this lessee's. */
    public function acknowledge(int $violationId, int $lesseeId): bool
    {
        return $this->execute(
            'UPDATE violations v
             JOIN tenants t ON t.id = v.tenant_id
             SET v.acknowledged = 1, v.acknowledged_at = NOW()
             WHERE v.id = ? AND t.lessee_id = ?',
            [$violationId, $lesseeId]
        ) > 0;
    }

    public function dispute(int $violationId, int $lesseeId, string $reason): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.tenant_id, v.lessor_id, v.category, v.strike, t.tenant_name
             FROM violations v
             JOIN tenants t ON t.id = v.tenant_id
             WHERE v.id = ? AND t.lessee_id = ? AND (v.is_revoked = 0 OR v.is_revoked IS NULL)
             LIMIT 1'
        );
        $stmt->execute([$violationId, $lesseeId]);
        $viol = $stmt->fetch();
        if (!$viol) {
            return null;
        }

        $this->execute(
            'UPDATE violations SET disputed = 1, dispute_status = "pending", dispute_reason = ?, disputed_at = NOW() WHERE id = ?',
            [$reason, $violationId]
        );

        return $viol;
    }

    public function rejectDispute(int $violationId, int $lesseeId, ?string $note = null): bool
    {
        return $this->execute(
            'UPDATE violations v
             JOIN tenants t ON t.id = v.tenant_id
             SET v.disputed = 0, v.dispute_status = "rejected",
                 v.dispute_resolved_at = NOW(), v.dispute_resolution_note = ?
             WHERE v.id = ? AND t.lessee_id = ?',
            [$note, $violationId, $lesseeId]
        ) > 0;
    }
}
