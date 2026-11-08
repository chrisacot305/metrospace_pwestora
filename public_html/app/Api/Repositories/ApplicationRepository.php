<?php
namespace Api\Repositories;

use PDOException;

/** Table: applications */
final class ApplicationRepository extends Repository
{
    /** The only document columns that can be written (prevents column-name injection). */
    public const DOCUMENT_COLUMNS = [
        'valid_id'        => 'valid_id_path',
        'sec_dti'         => 'sec_dti_path',
        'business_permit' => 'business_permit_path',
        'bir_cert'        => 'bir_cert_path',
    ];

    public function hasPending(int $propertyId, int $lesseeId): bool
    {
        return $this->fetchValue(
            "SELECT id FROM applications WHERE property_id = ? AND lessee_id = ? AND status = 'pending'",
            [$propertyId, $lesseeId]
        ) !== false;
    }

    public function create(array $data): int
    {
        try {
            return $this->insert(
                'INSERT INTO applications
                    (property_id, lessor_id, lessee_id, tenant_name, business_name, term_months, rent, checklist_negotiation)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['property_id'], $data['lessor_id'], $data['lessee_id'], $data['tenant_name'],
                    $data['business_name'], $data['term_months'], $data['rent'], $data['checklist_negotiation'],
                ]
            );
        } catch (PDOException $e) {
            // Older databases without checklist_negotiation (run tools/migrate.php to add it).
            return $this->insert(
                'INSERT INTO applications
                    (property_id, lessor_id, lessee_id, tenant_name, business_name, term_months, rent)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $data['property_id'], $data['lessor_id'], $data['lessee_id'], $data['tenant_name'],
                    $data['business_name'], $data['term_months'], $data['rent'],
                ]
            );
        }
    }

    /** @param array $paths  field => relative path, e.g. ['valid_id' => 'uploads/...'] */
    public function setDocumentPaths(int $applicationId, array $paths): void
    {
        $sets = [];
        $params = [];
        foreach ($paths as $field => $path) {
            if (!isset(self::DOCUMENT_COLUMNS[$field])) {
                continue; // never build SQL from an unknown name
            }
            $sets[] = self::DOCUMENT_COLUMNS[$field] . ' = ?';
            $params[] = $path;
        }
        if (!$sets) {
            return;
        }
        $params[] = $applicationId;
        $this->execute('UPDATE applications SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public function listForLessee(int $lesseeId): array
    {
        try {
            return $this->fetchAll(
                'SELECT a.id, a.term_months, a.rent, a.status, a.submitted_at,
                        a.checklist_negotiation, a.lessor_rebuttal, a.rebuttal_at,
                        a.valid_id_path, a.sec_dti_path, a.business_permit_path, a.bir_cert_path,
                        p.name AS property_name, u.company_name AS lessor_name
                 FROM applications a
                 JOIN properties p ON p.id = a.property_id
                 JOIN users u ON u.id = a.lessor_id
                 WHERE a.lessee_id = ?
                 ORDER BY a.submitted_at DESC',
                [$lesseeId]
            );
        } catch (PDOException $e) {
            return $this->fetchAll(
                'SELECT a.id, a.term_months, a.rent, a.status, a.submitted_at,
                        p.name AS property_name, u.company_name AS lessor_name
                 FROM applications a
                 JOIN properties p ON p.id = a.property_id
                 JOIN users u ON u.id = a.lessor_id
                 WHERE a.lessee_id = ?
                 ORDER BY a.submitted_at DESC',
                [$lesseeId]
            );
        }
    }

    /** Only returns the application if it belongs to this lessee. */
    public function findForLessee(int $applicationId, int $lesseeId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM applications WHERE id = ? AND lessee_id = ?',
            [$applicationId, $lesseeId]
        );
    }

    public function updateNegotiation(int $applicationId, string $json): void
    {
        $this->execute('UPDATE applications SET checklist_negotiation = ? WHERE id = ?', [$json, $applicationId]);
    }
}
