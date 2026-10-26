<?php
/**
 * models/RevenueModel.php
 * Handles database queries for the Revenue & Commission dashboard.
 */
class RevenueModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPlatformFeePercent(): float {
        $stmt = $this->pdo->query('SELECT setting_value FROM platform_settings WHERE setting_key = "platform_fee_percent"');
        return (float) ($stmt->fetchColumn() ?: 5);
    }
    
    public function getAvailablePropertyTypes(): array {
        return $this->pdo->query(
            "SELECT DISTINCT type FROM properties WHERE type IS NOT NULL AND type != '' ORDER BY type"
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    private function buildWhereClause(?string $type, ?string $search, array &$params): string {
        $conditions = [];
        if ($type) {
            $conditions[] = 'p.type = ?';
            $params[] = $type;
        }
        if ($search) {
            $conditions[] = '(u.company_name LIKE ? OR p.name LIKE ?)';
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    }

    public function getSummaryStats(?string $type = null, ?string $search = null): array {
        $params = [];
        $where = $this->buildWhereClause($type, $search, $params);
        
        $sql = "SELECT COUNT(*) AS active_leases, COALESCE(SUM(a.rent),0) AS mrr, COALESCE(AVG(a.rent),0) AS avg_rent
                FROM tenants t 
                JOIN applications a ON a.id = t.application_id
                JOIN properties p ON p.id = t.property_id
                JOIN users u ON u.id = t.lessor_id" . $where;
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    public function getRevenueByType(?string $type = null, ?string $search = null): array {
        $params = [];
        $where = $this->buildWhereClause($type, $search, $params);
        
        $sql = "SELECT p.type, COALESCE(SUM(a.rent),0) AS total
                FROM tenants t
                JOIN applications a ON a.id = t.application_id
                JOIN properties p ON p.id = t.property_id
                JOIN users u ON u.id = t.lessor_id" . 
                $where . " GROUP BY p.type ORDER BY total DESC";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRevenueByLessor(?string $type = null, ?string $search = null, int $limit = 6): array {
        $params = [];
        $where = $this->buildWhereClause($type, $search, $params);
        
        $sql = "SELECT u.company_name, COALESCE(SUM(a.rent),0) AS total
                FROM tenants t
                JOIN applications a ON a.id = t.application_id
                JOIN users u ON u.id = t.lessor_id
                JOIN properties p ON p.id = t.property_id" . 
                $where . " GROUP BY u.company_name ORDER BY total DESC LIMIT $limit";
                
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}