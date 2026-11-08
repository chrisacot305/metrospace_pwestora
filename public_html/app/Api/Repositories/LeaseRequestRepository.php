<?php
namespace Api\Repositories;

/** Table: lease_requests */
final class LeaseRequestRepository extends Repository
{
    /** Contract details, only if this lease request belongs to the given lessee. */
    public function findContractDetailsForLessee(int $leaseId, int $lesseeId): ?array
    {
        return $this->fetchOne(
            "SELECT l.*, p.name AS property_name, p.address, p.asking_rent,
                    COALESCE(NULLIF(u1.company_name, ''), u1.full_name) AS lessor_name,
                    u2.full_name AS lessee_name
             FROM lease_requests l
             JOIN properties p ON l.property_id = p.id
             JOIN users u1 ON p.lessor_id = u1.id
             JOIN users u2 ON l.lessee_id = u2.id
             WHERE l.id = ? AND l.lessee_id = ?",
            [$leaseId, $lesseeId]
        );
    }

    public function saveContract(int $leaseId, string $contractHtml, string $status): void
    {
        $this->execute(
            'UPDATE lease_requests SET status = ?, contract_body = ? WHERE id = ?',
            [$status, $contractHtml, $leaseId]
        );
    }
}
