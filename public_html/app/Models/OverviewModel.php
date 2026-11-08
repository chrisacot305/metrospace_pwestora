<?php
namespace App\Models;

use PDO;
/**
 * app/Models/OverviewModel.php
 * Handles database operations for the Super Admin overview.
 */
class OverviewModel extends Model
{

    public function getTotalActiveLessors(): int {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE role = "lessor" AND status = "active"')->fetchColumn();
    }

    public function getTotalUniqueLessees(): int {
        return (int) $this->pdo->query('SELECT COUNT(DISTINCT id) FROM tenants')->fetchColumn();
    }

    public function getPendingLessorsCount(): int {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE role = "lessor" AND status = "pending"')->fetchColumn();
    }

    public function getPendingPropertiesCount(): int {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM properties WHERE status = "pending"')->fetchColumn();
    }

    public function getRecentPendingLessors(int $limit = 5): array {
        return $this->pdo->query(
            "SELECT id, company_name FROM users WHERE role = 'lessor' AND status = 'pending' ORDER BY created_at DESC LIMIT $limit"
        )->fetchAll();
    }

    public function getRecentPendingProperties(int $limit = 5): array {
        return $this->pdo->query(
            "SELECT id, name FROM properties WHERE status = 'pending' ORDER BY created_at DESC LIMIT $limit"
        )->fetchAll();
    }
}