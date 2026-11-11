<?php
namespace App\Models;

use PDO;
/**
 * app/Models/LessorOverviewModel.php
 * Handles database queries for the Lessor Dashboard Overview.
 */
class LessorOverviewModel extends Model
{

    public function getInstallmentStats(int $lessorId, string $since30): array {
        return [
            'total'    => $this->count('installment_requests', 'lessor_id = ?', [$lessorId]),
            'last_30d' => $this->count('installment_requests', 'lessor_id = ? AND created_at >= ?', [$lessorId, $since30]),
            'approved' => $this->count('installment_requests', 'lessor_id = ? AND status = "approved"', [$lessorId]),
            'pending'  => $this->count('installment_requests', 'lessor_id = ? AND status = "pending"', [$lessorId]),
            'rejected' => $this->count('installment_requests', 'lessor_id = ? AND status = "rejected"', [$lessorId]),
        ];
    }

    public function getMaintenanceStats(int $lessorId, string $since30): array {
        return [
            'total'     => $this->count('maintenance_tickets', 'lessor_id = ?', [$lessorId]),
            'last_30d'  => $this->count('maintenance_tickets', 'lessor_id = ? AND created_at >= ?', [$lessorId, $since30]),
            'completed' => $this->count('maintenance_tickets', 'lessor_id = ? AND stage = 7', [$lessorId]),
            'open'      => $this->count('maintenance_tickets', 'lessor_id = ? AND stage < 7', [$lessorId]),
            'overdue'   => $this->count('maintenance_tickets', 'lessor_id = ? AND stage < 7 AND sla_due_at IS NOT NULL AND sla_due_at < NOW()', [$lessorId]),
        ];
    }

    public function getViolationStats(int $lessorId, string $since30): array {
        return [
            'total'       => $this->count('violations', 'lessor_id = ?', [$lessorId]),
            'last_30d'    => $this->count('violations', 'lessor_id = ? AND issued_at >= ?', [$lessorId, $since30]),
            'strike1'     => $this->count('violations', 'lessor_id = ? AND strike = 1', [$lessorId]),
            'strike2plus' => $this->getStrike2Plus($lessorId),
            'evictions'   => $this->count('violations', 'lessor_id = ? AND strike >= 3', [$lessorId]),
        ];
    }
    
    // Fallback for the distinct count above
    public function getStrike2Plus(int $lessorId): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(DISTINCT tenant_id) FROM violations WHERE lessor_id = ? AND strike >= 2');
        $stmt->execute([$lessorId]);
        return (int) $stmt->fetchColumn();
    }

    public function getKpiStats(int $lessorId): array {
        return [
            'active_leases'  => $this->count('tenants', 'lessor_id = ?', [$lessorId]),
            'pending_apps'   => $this->count('applications', 'lessor_id = ? AND status = "pending"', [$lessorId]),
            'open_tickets'   => $this->count('maintenance_tickets', 'lessor_id = ? AND stage < 7', [$lessorId]),
            'open_violations'=> $this->count('violations', 'lessor_id = ?', [$lessorId]) // Assuming all are open for KPI
        ];
    }

    public function getPendingInstallments(int $lessorId): array {
        $stmt = $this->pdo->prepare(
            'SELECT ir.*, t.tenant_name FROM installment_requests ir
             JOIN tenants t ON t.id = ir.tenant_id
             WHERE ir.lessor_id = ? AND ir.status = "pending"'
        );
        $stmt->execute([$lessorId]);
        return $stmt->fetchAll();
    }

    public function getOpenTickets(int $lessorId): array {
        $stmt = $this->pdo->prepare(
            'SELECT mt.*, t.tenant_name FROM maintenance_tickets mt
             JOIN tenants t ON t.id = mt.tenant_id
             WHERE mt.lessor_id = ? AND mt.stage < 7'
        );
        $stmt->execute([$lessorId]);
        return $stmt->fetchAll();
    }

    public function getPendingPayments(int $lessorId): array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, t.tenant_name FROM payments p
             JOIN tenants t ON t.id = p.tenant_id
             WHERE p.lessor_id = ? AND (p.logged_by IS NULL OR p.note LIKE '[PENDING]%') AND (p.note IS NULL OR p.note NOT LIKE '[DECLINED%')
             ORDER BY p.id DESC"
        );
        $stmt->execute([$lessorId]);
        return $stmt->fetchAll();
    }

    private function count(string $table, string $where, array $params): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}