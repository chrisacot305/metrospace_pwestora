<?php
namespace App\Models;

/** Table: violations (strike system) */
class ViolationModel extends Model
{
    public function highestStrike(int $tenantId): int
    {
        return (int) $this->value(
            "SELECT COALESCE(MAX(strike), 0) FROM violations
             WHERE tenant_id = ?
               AND (is_paid = 0 OR is_paid IS NULL
                    OR (issued_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                        AND (description IS NULL OR description NOT LIKE '%[DECAYED]%')))",
            [$tenantId]
        );
    }

    /**
     * $clause (optional): ['key', 'contract_ref', 'text', 'ref'] — the contract clause that was violated,
     * stored as a snapshot so the record stays accurate even if the contract changes later.
     */
    public function create(int $lessorId, int $tenantId, string $category, int $strike, float $fine, ?array $clause = null): int
    {
        return $this->insert(
            'INSERT INTO violations (lessor_id, tenant_id, category, strike, fine_amount, clause_key, contract_ref, clause_text, clause_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$lessorId, $tenantId, $category, $strike, $fine,
             $clause['key'] ?? null, $clause['contract_ref'] ?? null, $clause['text'] ?? null, $clause['ref'] ?? null]
        );
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
