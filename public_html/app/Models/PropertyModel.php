<?php
namespace App\Models;

use PDO;
/**
 * app/Models/PropertyModel.php
 * Handles database operations for Property Verification.
 */
class PropertyModel extends Model
{

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

    /** id + name of every property this lessor owns (for dropdowns). */
    public function listForLessor(int $lessorId): array
    {
        return $this->all('SELECT id, name FROM properties WHERE lessor_id = ? ORDER BY name', [$lessorId]);
    }

    public function verifiedForLessor(int $lessorId): array
    {
        return $this->all("SELECT id, name FROM properties WHERE lessor_id = ? AND status = 'verified' ORDER BY name", [$lessorId]);
    }

    public function isOwnedBy(int $propertyId, int $lessorId): bool
    {
        return $this->value('SELECT id FROM properties WHERE id = ? AND lessor_id = ?', [$propertyId, $lessorId]) !== false;
    }

    public function isVerifiedAndOwnedBy(int $propertyId, int $lessorId): bool
    {
        return $this->value("SELECT id FROM properties WHERE id = ? AND lessor_id = ? AND status = 'verified'", [$propertyId, $lessorId]) !== false;
    }

    /** "My Properties" list with photo count, cover photo and current tenant. */
    public function listingsForLessor(int $lessorId): array
    {
        return $this->all(
            'SELECT p.*, (SELECT COUNT(*) FROM property_photos pp WHERE pp.property_id = p.id) AS photo_count,
                    (SELECT photo_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.id ASC LIMIT 1) AS cover_photo,
                    (SELECT COUNT(*) FROM tenants t WHERE t.property_id = p.id AND t.status = "active") AS active_tenants_count,
                    (SELECT t.tenant_name FROM tenants t WHERE t.property_id = p.id ORDER BY t.created_at DESC LIMIT 1) AS current_tenant
             FROM properties p WHERE p.lessor_id = ? ORDER BY p.created_at DESC',
            [$lessorId]
        );
    }

    public function photosWithIds(int $propertyId): array
    {
        return $this->all('SELECT id, photo_path FROM property_photos WHERE property_id = ? ORDER BY id ASC', [$propertyId]);
    }

    public function create(int $lessorId, array $f): int
    {
        $totalUnits = isset($f['total_units']) ? max(1, (int)$f['total_units']) : 1;
        $availUnits = isset($f['available_units']) ? max(0, min($totalUnits, (int)$f['available_units'])) : $totalUnits;
        $occupancyStatus = ($availUnits > 0) ? 'available' : 'occupied';

        return $this->insert(
            "INSERT INTO properties (lessor_id, name, type, address, latitude, longitude, asking_rent, floor_area, lease_term, escalation, description, total_units, available_units, occupancy_status, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [$lessorId, $f['name'], $f['type'], $f['address'], $f['lat'], $f['lng'], $f['asking_rent'],
             $f['floor_area'], $f['lease_term'], $f['escalation'], $f['description'], $totalUnits, $availUnits, $occupancyStatus]
        );
    }

    public function updateDetails(int $propertyId, int $lessorId, array $f): void
    {
        $totalUnits = isset($f['total_units']) ? max(1, (int)$f['total_units']) : 1;
        $availUnits = isset($f['available_units']) ? max(0, min($totalUnits, (int)$f['available_units'])) : $totalUnits;
        $occupancyStatus = ($availUnits > 0) ? 'available' : 'occupied';

        $this->run(
            'UPDATE properties
             SET name = ?, type = ?, address = ?, latitude = ?, longitude = ?, asking_rent = ?,
                 floor_area = ?, lease_term = ?, escalation = ?, description = ?,
                 total_units = ?, available_units = ?, occupancy_status = ?
             WHERE id = ? AND lessor_id = ?',
            [$f['name'], $f['type'], $f['address'], $f['lat'], $f['lng'], $f['asking_rent'],
             $f['floor_area'], $f['lease_term'], $f['escalation'], $f['description'],
             $totalUnits, $availUnits, $occupancyStatus, $propertyId, $lessorId]
        );
    }

    public function setDocuments(int $propertyId, string $titleDocPath, string $taxDecPath): void
    {
        $this->run('UPDATE properties SET title_doc_path = ?, tax_dec_path = ? WHERE id = ?', [$titleDocPath, $taxDecPath, $propertyId]);
    }

    public function addPhoto(int $propertyId, string $path): void
    {
        $this->run('INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)', [$propertyId, $path]);
    }

    public function photoBelongsToLessor(int $photoId, int $propertyId, int $lessorId): bool
    {
        return $this->value(
            'SELECT pp.id FROM property_photos pp JOIN properties p ON p.id = pp.property_id WHERE pp.id = ? AND p.id = ? AND p.lessor_id = ?',
            [$photoId, $propertyId, $lessorId]
        ) !== false;
    }

    public function deletePhoto(int $photoId): void
    {
        $this->run('DELETE FROM property_photos WHERE id = ?', [$photoId]);
    }
        /**
     * Verified listings of active lessors for the public landing page (newest first).
     * Only public data: no tenant names, no documents.
     */
    public function publicListings(int $limit = 12): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.id, p.name, p.type, p.address, p.asking_rent, p.floor_area, p.lease_term, p.escalation,
                    p.total_units, p.available_units,
                    p.description, p.latitude, p.longitude, p.occupancy_status, p.created_at,
                    (SELECT GROUP_CONCAT(pp.photo_path ORDER BY pp.id ASC SEPARATOR '|')
                       FROM property_photos pp WHERE pp.property_id = p.id) AS photo_paths,
                    EXISTS(SELECT 1 FROM tenants t
                            WHERE t.property_id = p.id AND (t.end_date IS NULL OR t.end_date >= CURDATE())) AS has_active_tenant
             FROM properties p
             JOIN users u ON u.id = p.lessor_id
             WHERE p.status = 'verified' AND u.status = 'active'
             ORDER BY p.created_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    
}


