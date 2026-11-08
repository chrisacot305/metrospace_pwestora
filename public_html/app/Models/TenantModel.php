<?php
namespace App\Models;

/** Table: tenants (an active lease between a lessor and a tenant) */
class TenantModel extends Model
{
    /** Minimal row if this tenant belongs to the lessor, else null. */
    public function findOwned(int $tenantId, int $lessorId): ?array
    {
        return $this->one('SELECT id, tenant_name FROM tenants WHERE id = ? AND lessor_id = ?', [$tenantId, $lessorId]);
    }

    public function createFromApplication(int $lessorId, int $propertyId, int $appId, ?int $lesseeId, string $tenantName): int
    {
        return $this->insert(
            'INSERT INTO tenants (lessor_id, property_id, application_id, lessee_id, tenant_name) VALUES (?, ?, ?, ?, ?)',
            [$lessorId, $propertyId, $appId, $lesseeId, $tenantName]
        );
    }

    /** id, tenant_name, property_id — for dropdowns. */
    public function listForLessor(int $lessorId): array
    {
        return $this->all('SELECT id, tenant_name, property_id FROM tenants WHERE lessor_id = ? ORDER BY tenant_name', [$lessorId]);
    }

    /** Same list plus the contracted rent (payments form). */
    public function listWithRent(int $lessorId): array
    {
        return $this->all(
            'SELECT t.id, t.tenant_name, t.property_id, a.rent
             FROM tenants t
             LEFT JOIN applications a ON t.application_id = a.id
             WHERE t.lessor_id = ?
             ORDER BY t.tenant_name',
            [$lessorId]
        );
    }

    /** Same list plus the current deposit balance (deposits form). */
    public function listWithDeposit(int $lessorId): array
    {
        return $this->all(
            'SELECT id, tenant_name, property_id, security_deposit FROM tenants
             WHERE lessor_id = ?
             ORDER BY tenant_name',
            [$lessorId]
        );
    }

    /** Tenants with property and unit info (messages screen). */
    public function listWithProperty(int $lessorId): array
    {
        return $this->all(
            'SELECT t.id, t.tenant_name, t.business_name, t.unit_label, t.property_id, p.name AS property_name
             FROM tenants t
             JOIN properties p ON p.id = t.property_id
             WHERE t.lessor_id = ?
             ORDER BY p.name ASC, t.tenant_name ASC',
            [$lessorId]
        );
    }

    /** Full roster with lease terms, negotiation history and last verified payment. */
    public function rosterForLessor(int $lessorId): array
    {
        return $this->all(
            "SELECT t.id, t.tenant_name, t.created_at, t.start_date, t.end_date, t.application_id, t.lessee_id,
                    t.offline_email, t.offline_phone, p.name AS property_name,
                    a.term_months, a.rent, a.business_name, a.submitted_at,
                    a.checklist_negotiation, a.lessor_rebuttal, a.rebuttal_at,
                    u.email AS lessee_email, u.phone AS lessee_phone,
                    (
                        SELECT MAX(pay.paid_at)
                        FROM payments pay
                        WHERE pay.tenant_id = t.id
                          AND pay.lessor_id = t.lessor_id
                          AND pay.logged_by IS NOT NULL
                          AND (pay.note IS NULL OR pay.note NOT LIKE '[DECLINED%')
                    ) AS last_paid_at
             FROM tenants t
             JOIN properties p ON p.id = t.property_id
             LEFT JOIN applications a ON a.id = t.application_id
             LEFT JOIN users u ON u.id = t.lessee_id
             WHERE t.lessor_id = ?
             ORDER BY t.created_at DESC",
            [$lessorId]
        );
    }

    /** Marks the lease as ended (end date = yesterday). */
    public function endLease(int $tenantId, int $lessorId, string $endDate): void
    {
        $this->run('UPDATE tenants SET end_date = ? WHERE id = ? AND lessor_id = ?', [$endDate, $tenantId, $lessorId]);
    }

    /** Tenant + linked app-user contact details (Edit Tenant screen). */
    public function findWithContact(int $tenantId, int $lessorId): ?array
    {
        return $this->one(
            'SELECT t.*, u.email, u.phone FROM tenants t LEFT JOIN users u ON t.lessee_id = u.id WHERE t.id = ? AND t.lessor_id = ?',
            [$tenantId, $lessorId]
        );
    }

    public function updateDetails(int $tenantId, int $lessorId, array $f): void
    {
        $this->run(
            'UPDATE tenants
             SET tenant_name = ?, business_name = ?, offline_email = ?, offline_phone = ?, start_date = ?, end_date = ?, monthly_rent = ?, due_day = ?
             WHERE id = ? AND lessor_id = ?',
            [$f['tenant_name'], $f['business_name'], $f['offline_email'], $f['offline_phone'], $f['start_date'],
             $f['end_date'], $f['monthly_rent'], $f['due_day'], $tenantId, $lessorId]
        );
    }

    public function addToSecurityDeposit(int $tenantId, float $amount): void
    {
        $this->run('UPDATE tenants SET security_deposit = COALESCE(security_deposit, 0) + ? WHERE id = ?', [$amount, $tenantId]);
    }

    public function subtractFromSecurityDeposit(int $tenantId, float $amount): void
    {
        $this->run('UPDATE tenants SET security_deposit = GREATEST(0, COALESCE(security_deposit, 0) - ?) WHERE id = ?', [$amount, $tenantId]);
    }

    /** Phone number of the app user linked to this tenant (for SMS), or null. */
    public function lesseePhone(int $tenantId): ?string
    {
        $phone = $this->value('SELECT u.phone FROM tenants t JOIN users u ON u.id = t.lessee_id WHERE t.id = ?', [$tenantId]);
        return $phone === false ? null : (string) $phone;
    }

    /**
     * When a negotiated contract is finalized, its key terms become the tenant's
     * official terms (rent reminders, due dates and the roster read these columns).
     */
    public function syncFromContract(int $tenantId, int $lessorId, float $rent, ?string $startDate, ?string $endDate, int $dueDay): void
    {
        $this->run(
            'UPDATE tenants SET monthly_rent = ?, start_date = ?, end_date = ?, due_day = ? WHERE id = ? AND lessor_id = ?',
            [$rent, $startDate, $endDate, $dueDay, $tenantId, $lessorId]
        );
    }

    /** Everything needed to print the lease contract. */
    public function leaseDocument(int $tenantId): ?array
    {
        return $this->one(
            "SELECT t.*, p.name AS property_name, p.address AS property_address, p.type AS property_type,
                    u.company_name AS lessor_name,
                    lessee.full_name AS tenant_full_name,
                    a.rent, a.term_months, a.checklist_negotiation, a.lessor_rebuttal, a.rebuttal_at,
                    a.submitted_at, a.business_name AS app_business_name
             FROM tenants t
             JOIN properties p ON p.id = t.property_id
             JOIN users u ON u.id = t.lessor_id
             LEFT JOIN users lessee ON lessee.id = t.lessee_id
             LEFT JOIN applications a ON a.id = t.application_id
             WHERE t.id = ?",
            [$tenantId]
        );
    }
}
