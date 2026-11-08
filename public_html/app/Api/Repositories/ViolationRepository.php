<?php
namespace Api\Repositories;

/** Table: violations */
final class ViolationRepository extends Repository
{
    private const LIST_SELECT =
        'SELECT v.id, v.tenant_id, v.lessor_id, u.company_name AS lessor_name,
                v.category, v.strike, v.acknowledged, v.issued_at,
                v.contract_ref, v.clause_text, v.clause_ref
         FROM violations v
         JOIN users u ON u.id = v.lessor_id';

    public function listForTenant(int $tenantId): array
    {
        return $this->fetchAll(self::LIST_SELECT . ' WHERE v.tenant_id = ? ORDER BY v.issued_at DESC', [$tenantId]);
    }

    public function listForLessee(int $lesseeId): array
    {
        return $this->fetchAll(
            self::LIST_SELECT . ' JOIN tenants t ON t.id = v.tenant_id WHERE t.lessee_id = ? ORDER BY v.issued_at DESC',
            [$lesseeId]
        );
    }

    /**
     * Unpaid strike 2+ violations with their fine.
     * Default fines when none is set: strike 2 = 500, strike 3+ = 1500.
     */
    public function unpaidFinesForTenant(int $tenantId): array
    {
        return $this->fetchAll(
            'SELECT id, category, strike,
                    CASE
                        WHEN fine_amount > 0 THEN fine_amount
                        WHEN strike = 2 THEN 500.00
                        WHEN strike >= 3 THEN 1500.00
                        ELSE 0.00
                    END AS fine_amount,
                    issued_at
             FROM violations
             WHERE tenant_id = ? AND (is_paid = 0 OR is_paid IS NULL) AND strike >= 2
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
             SET v.acknowledged = 1
             WHERE v.id = ? AND t.lessee_id = ?',
            [$violationId, $lesseeId]
        ) > 0;
    }
}
