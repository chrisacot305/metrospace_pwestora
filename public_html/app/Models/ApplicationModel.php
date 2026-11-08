<?php
namespace App\Models;

/** Table: applications (lease applications sent to a lessor) */
class ApplicationModel extends Model
{
    public function findForLessor(int $appId, int $lessorId): ?array
    {
        return $this->one('SELECT * FROM applications WHERE id = ? AND lessor_id = ?', [$appId, $lessorId]);
    }

    public function setStatus(int $appId, string $status): void
    {
        $this->run('UPDATE applications SET status = ? WHERE id = ?', [$status, $appId]);
    }

    public function saveRebuttal(int $appId, int $lessorId, string $rebuttal, string $negotiationJson): void
    {
        $this->run(
            'UPDATE applications SET lessor_rebuttal = ?, rebuttal_at = NOW(), checklist_negotiation = ? WHERE id = ? AND lessor_id = ?',
            [$rebuttal, $negotiationJson, $appId, $lessorId]
        );
    }

    /** Application typed in by the lessor (walk-in tenant). */
    public function createManual(int $propertyId, int $lessorId, string $tenantName, string $business, int $term, float $rent): int
    {
        return $this->insert(
            'INSERT INTO applications (property_id, lessor_id, tenant_name, business_name, term_months, rent)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$propertyId, $lessorId, $tenantName, $business, $term, $rent]
        );
    }

    public function listForLessor(int $lessorId): array
    {
        return $this->all(
            'SELECT a.*, p.name AS property_name, t.id AS tenant_id
             FROM applications a
             JOIN properties p ON p.id = a.property_id
             LEFT JOIN tenants t ON t.application_id = a.id
             WHERE a.lessor_id = ?
             ORDER BY a.submitted_at DESC',
            [$lessorId]
        );
    }
}
