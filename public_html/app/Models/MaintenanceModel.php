<?php
namespace App\Models;

/** Table: maintenance_tickets */
class MaintenanceModel extends Model
{
    public function findForLessor(int $ticketId, int $lessorId): ?array
    {
        return $this->one('SELECT * FROM maintenance_tickets WHERE id = ? AND lessor_id = ?', [$ticketId, $lessorId]);
    }

    public function setStage(int $ticketId, int $stage, ?string $contractor = null): void
    {
        if ($contractor !== null) {
            $this->run('UPDATE maintenance_tickets SET stage = ?, contractor = ? WHERE id = ?', [$stage, $contractor, $ticketId]);
        } else {
            $this->run('UPDATE maintenance_tickets SET stage = ? WHERE id = ?', [$stage, $ticketId]);
        }
    }

    public function delete(int $ticketId): void
    {
        $this->run('DELETE FROM maintenance_tickets WHERE id = ?', [$ticketId]);
    }

    public function create(int $lessorId, int $tenantId, string $category, string $priority, string $title, ?string $contractor, ?string $slaDue): int
    {
        return $this->insert(
            'INSERT INTO maintenance_tickets (lessor_id, tenant_id, category, priority, title, contractor, sla_due_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$lessorId, $tenantId, $category, $priority, $title, $contractor, $slaDue]
        );
    }

    public function listForLessor(int $lessorId): array
    {
        return $this->all(
            'SELECT mt.*, t.tenant_name, p.name AS property_name
             FROM maintenance_tickets mt
             JOIN tenants t ON t.id = mt.tenant_id
             LEFT JOIN properties p ON p.id = t.property_id
             WHERE mt.lessor_id = ? ORDER BY mt.created_at DESC',
            [$lessorId]
        );
    }
}
