<?php
namespace App\Models;

/** Table: deposits (security deposit ledger) */
class DepositModel extends Model
{
    public function findLocked(int $depositId, int $lessorId): ?array
    {
        return $this->one("SELECT * FROM deposits WHERE id = ? AND lessor_id = ? AND status = 'locked'", [$depositId, $lessorId]);
    }

    public function release(int $depositId, string $note): void
    {
        $this->run("UPDATE deposits SET status = 'released', note = ?, released_at = NOW() WHERE id = ?", [$note, $depositId]);
    }

    public function create(int $lessorId, int $tenantId, float $amount, string $method): int
    {
        return $this->insert('INSERT INTO deposits (lessor_id, tenant_id, amount, method) VALUES (?, ?, ?, ?)', [$lessorId, $tenantId, $amount, $method]);
    }

    /** Deposit top-up the tenant paid online, recorded when the lessor verifies it. */
    public function createLockedTopUp(int $lessorId, int $tenantId, float $amount, string $method, string $note): int
    {
        return $this->insert(
            "INSERT INTO deposits (lessor_id, tenant_id, amount, method, status, note, created_at)
             VALUES (?, ?, ?, ?, 'locked', ?, NOW())",
            [$lessorId, $tenantId, $amount, $method, $note]
        );
    }

    public function listForLessor(int $lessorId): array
    {
        return $this->all(
            'SELECT d.*, t.tenant_name, p.name AS property_name
             FROM deposits d
             JOIN tenants t ON t.id = d.tenant_id
             LEFT JOIN properties p ON p.id = t.property_id
             WHERE d.lessor_id = ? ORDER BY d.id DESC',
            [$lessorId]
        );
    }
}
