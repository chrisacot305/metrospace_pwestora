<?php
namespace Api\Repositories;

/** Table: tenants (a lessee's active lease on a property) */
final class TenantRepository extends Repository
{
    private const TENANCY_SELECT =
        'SELECT t.id AS tenant_id, t.id, t.lessor_id, a.rent, u.company_name AS lessor_name
         FROM tenants t
         LEFT JOIN applications a ON a.id = t.application_id
         LEFT JOIN users u ON u.id = t.lessor_id';

    /**
     * The lessee's tenancy: a specific one if $tenantId > 0, otherwise the newest.
     * Only matches tenancies where tenants.lessee_id is this user.
     */
    public function findForLessee(int $lesseeId, int $tenantId = 0): ?array
    {
        return $this->pick(self::TENANCY_SELECT . ' WHERE t.lessee_id = ?', [$lesseeId], $tenantId);
    }

    /**
     * Same as findForLessee() but also matches tenancies created from an application
     * this lessee submitted. $demoName (demo mode only) also matches by tenant name.
     */
    public function findForLesseeIncludingApplications(int $lesseeId, int $tenantId = 0, ?string $demoName = null): ?array
    {
        $where = '(t.lessee_id = ? OR a.lessee_id = ?';
        $params = [$lesseeId, $lesseeId];
        if ($demoName !== null && $demoName !== '') {
            $where .= ' OR t.tenant_name = ?';
            $params[] = $demoName;
        }
        $where .= ')';

        return $this->pick(self::TENANCY_SELECT . ' WHERE ' . $where, $params, $tenantId);
    }

    /** Demo mode only: newest tenant record of anyone. */
    public function findLatestAny(): ?array
    {
        return $this->fetchOne(self::TENANCY_SELECT . ' ORDER BY t.id DESC LIMIT 1');
    }

    public function idsForLessee(int $lesseeId): array
    {
        return array_map('intval', $this->fetchColumnList('SELECT id FROM tenants WHERE lessee_id = ?', [$lesseeId]));
    }

    /** Every lease of this lessee with property + lessor info, newest first. */
    public function leasesForLessee(int $lesseeId): array
    {
        return $this->fetchAll(
            "SELECT t.id AS tenant_id, t.property_id, t.lessor_id, t.due_day, t.created_at AS since,
                    t.start_date, t.end_date, t.security_deposit, t.advance_rent, t.contract_pdf,
                    COALESCE(NULLIF(t.business_name, ''), a.business_name, '') AS business_name,
                    t.unit_label,
                    p.name AS property_name, p.address AS property_address, p.type AS property_type,
                    (SELECT photo_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.id ASC LIMIT 1) AS cover_photo,
                    u.company_name AS lessor_name,
                    a.rent, a.term_months, a.submitted_at AS applied_at
             FROM tenants t
             JOIN properties p ON p.id = t.property_id
             JOIN users u ON u.id = t.lessor_id
             LEFT JOIN applications a ON a.id = t.application_id
             WHERE t.lessee_id = ?
             ORDER BY t.created_at DESC",
            [$lesseeId]
        );
    }

    /** Data needed to show the lease contract. */
    public function contractForLessee(int $lesseeId, int $tenantId = 0): ?array
    {
        $sql = 'SELECT t.id AS tenant_id, t.created_at AS since,
                       p.name AS property_name, p.address AS property_address, p.type AS property_type,
                       u.company_name AS lessor_name,
                       a.rent, a.term_months
                FROM tenants t
                JOIN properties p ON p.id = t.property_id
                JOIN users u ON u.id = t.lessor_id
                LEFT JOIN applications a ON a.id = t.application_id
                WHERE t.lessee_id = ?';
        return $this->pick($sql, [$lesseeId], $tenantId);
    }

    /** Locks the tenant row until the current transaction ends (prevents double deductions). */
    public function lockSecurityDeposit(int $tenantId): float
    {
        return (float) $this->fetchValue('SELECT security_deposit FROM tenants WHERE id = ? FOR UPDATE', [$tenantId]);
    }

    public function updateSecurityDeposit(int $tenantId, float $amount): void
    {
        $this->execute('UPDATE tenants SET security_deposit = ? WHERE id = ?', [$amount, $tenantId]);
    }

    /** Adds "AND t.id = ?" when a tenant id is given, otherwise picks the newest. */
    private function pick(string $sql, array $params, int $tenantId): ?array
    {
        if ($tenantId > 0) {
            $sql .= ' AND t.id = ? LIMIT 1';
            $params[] = $tenantId;
        } else {
            $sql .= ' ORDER BY t.created_at DESC LIMIT 1';
        }
        return $this->fetchOne($sql, $params);
    }
}
