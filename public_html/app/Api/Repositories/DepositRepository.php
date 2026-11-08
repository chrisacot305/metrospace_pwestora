<?php
namespace Api\Repositories;

/** Table: deposits */
final class DepositRepository extends Repository
{
    public function recordAutomatedRelease(int $lessorId, int $tenantId, float $amount, string $note): int
    {
        return $this->insert(
            "INSERT INTO deposits (lessor_id, tenant_id, amount, method, status, note, released_at)
             VALUES (?, ?, ?, 'Automated Escrow', 'released', ?, NOW())",
            [$lessorId, $tenantId, $amount, $note]
        );
    }
}
