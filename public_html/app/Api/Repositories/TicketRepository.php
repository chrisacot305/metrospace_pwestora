<?php
namespace Api\Repositories;

/** Table: maintenance_tickets */
final class TicketRepository extends Repository
{
    /** Tickets that are not finished yet (stage 7 = closed). */
    public function countOpenForTenant(int $tenantId): int
    {
        return (int) $this->fetchValue(
            'SELECT COUNT(*) FROM maintenance_tickets WHERE tenant_id = ? AND stage < 7',
            [$tenantId]
        );
    }

    public function create(int $lessorId, int $tenantId, string $category, string $priority, string $title): int
    {
        return $this->insert(
            'INSERT INTO maintenance_tickets (lessor_id, tenant_id, category, priority, title) VALUES (?, ?, ?, ?, ?)',
            [$lessorId, $tenantId, $category, $priority, $title]
        );
    }

    public function setPhotos(int $ticketId, array $photoPaths): void
    {
        $this->execute('UPDATE maintenance_tickets SET photos = ? WHERE id = ?', [json_encode($photoPaths), $ticketId]);
    }

    public function listForTenant(int $tenantId): array
    {
        return $this->fetchAll(
            'SELECT id, category, priority, title, stage, contractor, photos, created_at
             FROM maintenance_tickets WHERE tenant_id = ? ORDER BY created_at DESC',
            [$tenantId]
        );
    }
}
