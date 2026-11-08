<?php
namespace App\Models;

use PDO;
/**
 * app/Models/AuditModel.php
 * Handles database operations for the Audit Logs.
 */
class AuditModel extends Model
{

    public function getRecentLogs(int $limit = 200): array {
        $stmt = $this->pdo->prepare(
            "SELECT al.*, u.company_name, u.full_name, u.role AS actor_role
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.actor_id
             ORDER BY al.created_at DESC
             LIMIT :limit"
        );
        
        // PDO bindParam requires explicitly setting the parameter type for LIMIT
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function log(?int $actorId, string $action, string $details = ''): void
    {
        $this->run('INSERT INTO audit_logs (actor_id, action, details) VALUES (?, ?, ?)', [$actorId, $action, $details]);
    }

    /** Entire audit log for the CSV export (no row limit). */
    public function allForExport(): array
    {
        return $this->all(
            "SELECT al.*, u.company_name, u.full_name, u.role AS actor_role
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.actor_id
             ORDER BY al.created_at DESC"
        );
    }
}
