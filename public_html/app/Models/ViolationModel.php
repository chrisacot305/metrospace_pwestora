<?php
namespace App\Models;

/** Table: violations (strike system) */
class ViolationModel extends Model
{
    private function ensureColumns(): void
    {
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;

        $cols = [
            'clause_key'              => 'VARCHAR(80) NULL',
            'contract_ref'            => 'VARCHAR(60) NULL',
            'clause_text'             => 'TEXT NULL',
            'clause_ref'              => 'VARCHAR(300) NULL',
            'fine_amount'             => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'is_paid'                 => 'TINYINT(1) NOT NULL DEFAULT 0',
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
            } catch (\Throwable $e) {
                // column already exists or restricted
            }
        }
    }

    public function highestStrike(int $tenantId): int
    {
        $this->ensureColumns();
        try {
            return (int) $this->value(
                "SELECT COALESCE(MAX(strike), 0) FROM violations
                 WHERE tenant_id = ?
                   AND (is_revoked = 0 OR is_revoked IS NULL)
                   AND (disputed = 0 OR disputed IS NULL OR dispute_status = 'rejected')
                   AND (is_paid = 0 OR is_paid IS NULL
                        OR (issued_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                            AND (description IS NULL OR description NOT LIKE '%[DECAYED]%')))",
                [$tenantId]
            );
        } catch (\Throwable $e) {
            return (int) $this->value(
                "SELECT COALESCE(MAX(strike), 0) FROM violations
                 WHERE tenant_id = ?
                   AND (is_revoked = 0 OR is_revoked IS NULL)
                   AND (disputed = 0 OR disputed IS NULL OR dispute_status = 'rejected')
                   AND (is_paid = 0 OR is_paid IS NULL
                        OR (issued_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                            AND (description IS NULL OR description NOT LIKE '%[DECAYED]%')))",
                [$tenantId]
            );
        }
    }

    public function dispute(int $id, int $tenantId, string $reason): bool
    {
        $this->ensureColumns();
        try {
            return $this->run(
                'UPDATE violations
                 SET disputed = 1, dispute_status = "pending", dispute_reason = ?, disputed_at = NOW()
                 WHERE id = ? AND tenant_id = ? AND (is_revoked = 0 OR is_revoked IS NULL)',
                [$reason, $id, $tenantId]
            ) > 0;
        } catch (\Throwable $e) {
            error_log('ViolationModel dispute error: ' . $e->getMessage());
            return false;
        }
    }

    public function revoke(int $id, int $lessorId, ?string $reason = null): bool
    {
        $this->ensureColumns();
        try {
            return $this->run(
                'UPDATE violations
                 SET is_revoked = 1, acknowledged = 1, is_paid = 1, disputed = 0, dispute_status = "approved",
                     revoked_at = NOW(), revocation_reason = ?, dispute_resolved_at = NOW()
                 WHERE id = ? AND lessor_id = ?',
                [$reason, $id, $lessorId]
            ) > 0;
        } catch (\Throwable $e) {
            error_log('ViolationModel revoke error: ' . $e->getMessage());
            return false;
        }
    }

    public function rejectDispute(int $id, int $lessorId, ?string $reason = null): bool
    {
        $this->ensureColumns();
        try {
            return $this->run(
                'UPDATE violations
                 SET disputed = 0, dispute_status = "rejected",
                     dispute_resolved_at = NOW(), dispute_resolution_note = ?
                 WHERE id = ? AND lessor_id = ?',
                [$reason, $id, $lessorId]
            ) > 0;
        } catch (\Throwable $e) {
            error_log('ViolationModel rejectDispute error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * $clause (optional): ['key', 'contract_ref', 'text', 'ref'] — the contract clause that was violated,
     * stored as a snapshot so the record stays accurate even if the contract changes later.
     */
    public function create(int $lessorId, int $tenantId, string $category, int $strike, float $fine, ?array $clause = null): int
    {
        $this->ensureColumns();
        try {
            return $this->insert(
                'INSERT INTO violations (lessor_id, tenant_id, category, strike, fine_amount, clause_key, contract_ref, clause_text, clause_ref)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$lessorId, $tenantId, $category, $strike, $fine,
                 $clause['key'] ?? null, $clause['contract_ref'] ?? null, $clause['text'] ?? null, $clause['ref'] ?? null]
            );
        } catch (\Throwable $e) {
            try {
                return $this->insert(
                    'INSERT INTO violations (lessor_id, tenant_id, category, strike, fine_amount)
                     VALUES (?, ?, ?, ?, ?)',
                    [$lessorId, $tenantId, $category, $strike, $fine]
                );
            } catch (\Throwable $e2) {
                return $this->insert(
                    'INSERT INTO violations (lessor_id, tenant_id, category, strike)
                     VALUES (?, ?, ?, ?)',
                    [$lessorId, $tenantId, $category, $strike]
                );
            }
        }
    }

    public function listForLessor(int $lessorId): array
    {
        return $this->all(
            'SELECT v.*, t.tenant_name, p.name AS property_name
             FROM violations v
             JOIN tenants t ON t.id = v.tenant_id
             LEFT JOIN properties p ON p.id = t.property_id
             WHERE v.lessor_id = ? ORDER BY v.issued_at DESC',
            [$lessorId]
        );
    }

    public function findOwned(int $id, int $lessorId): ?array
    {
        return $this->one(
            'SELECT * FROM violations WHERE id = ? AND lessor_id = ?',
            [$id, $lessorId]
        );
    }

    /** Violation + tenant/property, only if it belongs to this lessor (eviction notice). */
    public function findNoticeForLessor(int $violationId, int $lessorId): ?array
    {
        return $this->one(
            'SELECT v.*, t.tenant_name, p.name AS property_name, p.address
             FROM violations v
             JOIN tenants t ON t.id = v.tenant_id
             JOIN properties p ON p.id = t.property_id
             WHERE v.id = ? AND v.lessor_id = ?',
            [$violationId, $lessorId]
        );
    }
}
