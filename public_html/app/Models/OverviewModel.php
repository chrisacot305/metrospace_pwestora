<?php
namespace App\Models;

use PDO;
/**
 * app/Models/OverviewModel.php
 * Handles database operations for the Super Admin overview
 * (KPI counts + the lists shown when a KPI card is clicked + platform search).
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
        return $this->limited(
            "SELECT id, company_name, full_name, email, created_at FROM users
             WHERE role = 'lessor' AND status = 'pending' ORDER BY created_at DESC LIMIT ?",
            [], $limit
        );
    }

    public function getRecentPendingProperties(int $limit = 5): array {
        return $this->limited(
            "SELECT p.id, p.name, p.type, p.created_at, u.company_name AS lessor_name
             FROM properties p JOIN users u ON u.id = p.lessor_id
             WHERE p.status = 'pending' ORDER BY p.created_at DESC LIMIT ?",
            [], $limit
        );
    }

    // ------------------------------------------------------------------ KPI drill-down lists

    /** Verified (active) lessors with their portfolio size. */
    public function listActiveLessors(int $limit = 300): array {
        return $this->limited(
            "SELECT u.id, u.company_name, u.full_name, u.email, u.phone, u.created_at,
                    (SELECT COUNT(*) FROM properties p WHERE p.lessor_id = u.id) AS property_count,
                    (SELECT COUNT(*) FROM properties p WHERE p.lessor_id = u.id AND p.status = 'verified') AS verified_count,
                    (SELECT COUNT(*) FROM tenants t WHERE t.lessor_id = u.id) AS tenant_count
             FROM users u
             WHERE u.role = 'lessor' AND u.status = 'active'
             ORDER BY u.company_name ASC
             LIMIT ?",
            [], $limit
        );
    }

    /** Every tenant record (the "Total Lessees" count) with property and lessor. */
    public function listLessees(int $limit = 300): array {
        return $this->limited(
            "SELECT t.id, t.tenant_name, t.business_name, t.unit_label, t.status, t.start_date, t.end_date, t.created_at,
                    t.lessee_id, p.id AS property_id, p.name AS property_name,
                    l.company_name AS lessor_name, ls.email AS lessee_email
             FROM tenants t
             LEFT JOIN properties p ON p.id = t.property_id
             LEFT JOIN users l ON l.id = t.lessor_id
             LEFT JOIN users ls ON ls.id = t.lessee_id
             ORDER BY t.created_at DESC
             LIMIT ?",
            [], $limit
        );
    }

    // ------------------------------------------------------------------ search (toolbar)

    /** Lessors (any status) matching name / company / email. */
    public function searchLessors(string $q, int $limit = 25): array {
        $like = '%' . $this->escapeLike($q) . '%';
        return $this->limited(
            "SELECT id, company_name, full_name, email, status, created_at,
                    (SELECT COUNT(*) FROM properties p WHERE p.lessor_id = users.id) AS property_count
             FROM users
             WHERE role = 'lessor' AND (company_name LIKE ? OR full_name LIKE ? OR email LIKE ?)
             ORDER BY company_name ASC LIMIT ?",
            [$like, $like, $like], $limit
        );
    }

    /** Tenants matching tenant / business name, property, or lessee e-mail. */
    public function searchLessees(string $q, int $limit = 25): array {
        $like = '%' . $this->escapeLike($q) . '%';
        return $this->limited(
            "SELECT t.id, t.tenant_name, t.business_name, t.status, t.start_date, t.end_date,
                    p.name AS property_name, l.company_name AS lessor_name, ls.email AS lessee_email
             FROM tenants t
             LEFT JOIN properties p ON p.id = t.property_id
             LEFT JOIN users l ON l.id = t.lessor_id
             LEFT JOIN users ls ON ls.id = t.lessee_id
             WHERE t.tenant_name LIKE ? OR t.business_name LIKE ? OR p.name LIKE ? OR ls.email LIKE ?
             ORDER BY t.tenant_name ASC LIMIT ?",
            [$like, $like, $like, $like], $limit
        );
    }

    /** Properties matching name / address / type. */
    public function searchProperties(string $q, int $limit = 25): array {
        $like = '%' . $this->escapeLike($q) . '%';
        return $this->limited(
            "SELECT p.id, p.name, p.type, p.address, p.status, u.company_name AS lessor_name
             FROM properties p JOIN users u ON u.id = p.lessor_id
             WHERE p.name LIKE ? OR p.address LIKE ? OR p.type LIKE ?
             ORDER BY p.name ASC LIMIT ?",
            [$like, $like, $like], $limit
        );
    }

    private function escapeLike(string $q): string {
        return addcslashes($q, '%_\\');
    }

    /** Runs a query whose LAST placeholder is the LIMIT (bound as an integer). */
    private function limited(string $sql, array $params, int $limit): array {
        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p, PDO::PARAM_STR);
        }
        $stmt->bindValue($i, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
