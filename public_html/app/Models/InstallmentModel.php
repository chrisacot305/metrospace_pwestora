<?php
namespace App\Models;

/** Table: installment_requests (rent restructuring plans) */
class InstallmentModel extends Model
{
    public function findForLessor(int $requestId, int $lessorId): ?array
    {
        return $this->one('SELECT * FROM installment_requests WHERE id = ? AND lessor_id = ?', [$requestId, $lessorId]);
    }

    public function setStatus(int $requestId, string $status): void
    {
        $this->run('UPDATE installment_requests SET status = ? WHERE id = ?', [$status, $requestId]);
    }

    public function create(int $lessorId, int $tenantId, string $reason, string $plan, float $amount): int
    {
        return $this->insert(
            'INSERT INTO installment_requests (lessor_id, tenant_id, reason, plan_label, amount) VALUES (?, ?, ?, ?, ?)',
            [$lessorId, $tenantId, $reason, $plan, $amount]
        );
    }

    public function listForLessor(int $lessorId): array
    {
        return $this->all(
            'SELECT ir.*, t.tenant_name, p.name AS property_name
             FROM installment_requests ir
             JOIN tenants t ON t.id = ir.tenant_id
             JOIN properties p ON p.id = t.property_id
             WHERE ir.lessor_id = ? ORDER BY ir.created_at DESC',
            [$lessorId]
        );
    }
}
