<?php
/**
 * models/PropertyModel.php
 * Handles database operations for Property Verification.
 */
class PropertyModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getPropertyWithLessor(int $propertyId) {
        $stmt = $this->pdo->prepare(
            'SELECT p.name, u.email, u.full_name 
             FROM properties p 
             JOIN users u ON u.id = p.lessor_id 
             WHERE p.id = ?'
        );
        $stmt->execute([$propertyId]);
        return $stmt->fetch();
    }

    public function updatePropertyStatus(int $propertyId, string $status): void {
        $this->pdo->prepare('UPDATE properties SET status = ? WHERE id = ?')->execute([$status, $propertyId]);
    }

    public function getAllProperties(): array {
        return $this->pdo->query(
            'SELECT p.*, u.company_name AS lessor_name
             FROM properties p
             JOIN users u ON u.id = p.lessor_id
             ORDER BY CASE WHEN p.status = "pending" THEN 0 ELSE 1 END, p.created_at DESC'
        )->fetchAll();
    }

    public function getPropertyPhotos(int $propertyId): array {
        $stmt = $this->pdo->prepare('SELECT photo_path FROM property_photos WHERE property_id = ? ORDER BY id ASC');
        $stmt->execute([$propertyId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}