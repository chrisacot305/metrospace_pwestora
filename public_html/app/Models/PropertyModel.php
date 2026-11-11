<?php
namespace App\Models;

use PDO;
/**
 * app/Models/PropertyModel.php
 * Handles database operations for properties, their photo gallery and verification.
 *
 * Photo gallery order: property_photos.sort_order ASC, then id ASC.
 * The first photo in that order is the COVER photo (sort_order 0).
 * Requires tools/migrate.php (Nov 2026) for the landmark / extra-info / photo columns.
 */
class PropertyModel extends Model
{
    /** Photo order used everywhere (cover first). */
    public const PHOTO_ORDER = 'sort_order ASC, id ASC';

    /** Photo categories shown in the gallery (key => label). */
    public const PHOTO_CATEGORIES = [
        'exterior'   => 'Exterior / Facade',
        'interior'   => 'Interior',
        'floor_plan' => 'Floor Plan',
        'amenity'    => 'Amenities',
        'surroundings' => 'Surroundings / Street',
        'other'      => 'Other',
    ];

    /** Extra-info columns saved by create() / updateDetails(). */
    private const EXTRA_COLUMNS = [
        'landmark_name', 'landmark_distance', 'floor_level', 'frontage', 'parking_slots',
        'utilities', 'amenities', 'permitted_use', 'operating_hours', 'deposit_terms',
    ];

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
            'SELECT p.*, u.company_name AS lessor_name,
                    (SELECT COUNT(*) FROM property_photos pp WHERE pp.property_id = p.id) AS photo_count
             FROM properties p
             JOIN users u ON u.id = p.lessor_id
             ORDER BY CASE WHEN p.status = "pending" THEN 0 ELSE 1 END, p.created_at DESC'
        )->fetchAll();
    }

    /** Photo paths only (cover first). */
    public function getPropertyPhotos(int $propertyId): array {
        $stmt = $this->pdo->prepare('SELECT photo_path FROM property_photos WHERE property_id = ? ORDER BY ' . self::PHOTO_ORDER);
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
                    (SELECT photo_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.sort_order ASC, pp.id ASC LIMIT 1) AS cover_photo,
                    (SELECT COUNT(*) FROM tenants t WHERE t.property_id = p.id AND t.status = "active") AS active_tenants_count,
                    (SELECT t.tenant_name FROM tenants t WHERE t.property_id = p.id ORDER BY t.created_at DESC LIMIT 1) AS current_tenant
             FROM properties p WHERE p.lessor_id = ? ORDER BY p.created_at DESC',
            [$lessorId]
        );
    }

    /** Full gallery rows (id, path, category, caption, sort_order), cover first. */
    public function photosWithIds(int $propertyId): array
    {
        return $this->all(
            'SELECT id, photo_path, category, caption, sort_order FROM property_photos WHERE property_id = ? ORDER BY ' . self::PHOTO_ORDER,
            [$propertyId]
        );
    }

    public function photoCount(int $propertyId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM property_photos WHERE property_id = ?', [$propertyId]);
    }

    public function create(int $lessorId, array $f): int
    {
        $totalUnits = isset($f['total_units']) ? max(1, (int)$f['total_units']) : 1;
        $availUnits = isset($f['available_units']) ? max(0, min($totalUnits, (int)$f['available_units'])) : $totalUnits;
        $occupancyStatus = ($availUnits > 0) ? 'available' : 'occupied';

        $extraCols = implode(', ', self::EXTRA_COLUMNS);
        $extraMarks = implode(', ', array_fill(0, count(self::EXTRA_COLUMNS), '?'));

        return $this->insert(
            "INSERT INTO properties (lessor_id, name, type, address, latitude, longitude, asking_rent, floor_area, lease_term, escalation, description, total_units, available_units, occupancy_status, status, $extraCols)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', $extraMarks)",
            array_merge(
                [$lessorId, $f['name'], $f['type'], $f['address'], $f['lat'], $f['lng'], $f['asking_rent'],
                 $f['floor_area'], $f['lease_term'], $f['escalation'], $f['description'], $totalUnits, $availUnits, $occupancyStatus],
                $this->extraValues($f)
            )
        );
    }

    public function updateDetails(int $propertyId, int $lessorId, array $f): void
    {
        $totalUnits = isset($f['total_units']) ? max(1, (int)$f['total_units']) : 1;
        $availUnits = isset($f['available_units']) ? max(0, min($totalUnits, (int)$f['available_units'])) : $totalUnits;
        $occupancyStatus = ($availUnits > 0) ? 'available' : 'occupied';

        $extraSet = implode(', ', array_map(static fn($c) => "$c = ?", self::EXTRA_COLUMNS));

        $this->run(
            "UPDATE properties
             SET name = ?, type = ?, address = ?, latitude = ?, longitude = ?, asking_rent = ?,
                 floor_area = ?, lease_term = ?, escalation = ?, description = ?,
                 total_units = ?, available_units = ?, occupancy_status = ?, $extraSet
             WHERE id = ? AND lessor_id = ?",
            array_merge(
                [$f['name'], $f['type'], $f['address'], $f['lat'], $f['lng'], $f['asking_rent'],
                 $f['floor_area'], $f['lease_term'], $f['escalation'], $f['description'],
                 $totalUnits, $availUnits, $occupancyStatus],
                $this->extraValues($f),
                [$propertyId, $lessorId]
            )
        );
    }

    private function extraValues(array $f): array
    {
        $out = [];
        foreach (self::EXTRA_COLUMNS as $col) {
            $out[] = $f[$col] ?? null;
        }
        return $out;
    }

    public function setDocuments(int $propertyId, string $titleDocPath, string $taxDecPath): void
    {
        $this->run('UPDATE properties SET title_doc_path = ?, tax_dec_path = ? WHERE id = ?', [$titleDocPath, $taxDecPath, $propertyId]);
    }

    // ------------------------------------------------------------------ landmark photo

    public function landmarkPhotoPath(int $propertyId): ?string
    {
        $v = $this->value('SELECT landmark_photo_path FROM properties WHERE id = ?', [$propertyId]);
        return ($v === false || $v === null || $v === '') ? null : (string) $v;
    }

    public function setLandmarkPhoto(int $propertyId, int $lessorId, ?string $path): void
    {
        $this->run('UPDATE properties SET landmark_photo_path = ? WHERE id = ? AND lessor_id = ?', [$path, $propertyId, $lessorId]);
    }

    // ------------------------------------------------------------------ gallery photos

    public function addPhoto(int $propertyId, string $path, string $category = 'exterior', ?string $caption = null): void
    {
        $category = array_key_exists($category, self::PHOTO_CATEGORIES) ? $category : 'exterior';
        // New photos go to the end of the gallery; the very first photo becomes the cover (0).
        $next = (int) $this->value('SELECT COALESCE(MAX(sort_order) + 1, 0) FROM property_photos WHERE property_id = ?', [$propertyId]);
        $this->run(
            'INSERT INTO property_photos (property_id, photo_path, category, caption, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$propertyId, $path, $category, $caption, $next]
        );
    }

    public function photoBelongsToLessor(int $photoId, int $propertyId, int $lessorId): bool
    {
        return $this->value(
            'SELECT pp.id FROM property_photos pp JOIN properties p ON p.id = pp.property_id WHERE pp.id = ? AND p.id = ? AND p.lessor_id = ?',
            [$photoId, $propertyId, $lessorId]
        ) !== false;
    }

    public function photoPath(int $photoId): ?string
    {
        $v = $this->value('SELECT photo_path FROM property_photos WHERE id = ?', [$photoId]);
        return $v === false ? null : (string) $v;
    }

    public function deletePhoto(int $photoId): void
    {
        $this->run('DELETE FROM property_photos WHERE id = ?', [$photoId]);
    }

    /** Makes this photo the cover: it gets sort_order 0, everything else keeps its order after it. */
    public function setCoverPhoto(int $photoId, int $propertyId): void
    {
        $ids = $this->column('SELECT id FROM property_photos WHERE property_id = ? ORDER BY ' . self::PHOTO_ORDER, [$propertyId]);
        $ids = array_values(array_filter(array_map('intval', $ids), static fn($id) => $id !== $photoId));
        array_unshift($ids, $photoId);
        $stmt = $this->pdo->prepare('UPDATE property_photos SET sort_order = ? WHERE id = ? AND property_id = ?');
        foreach ($ids as $i => $id) {
            $stmt->execute([$i, $id, $propertyId]);
        }
    }

    public function updatePhotoMeta(int $photoId, int $propertyId, string $category, ?string $caption): void
    {
        $category = array_key_exists($category, self::PHOTO_CATEGORIES) ? $category : 'other';
        $this->run('UPDATE property_photos SET category = ?, caption = ? WHERE id = ? AND property_id = ?', [$category, $caption, $photoId, $propertyId]);
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
                    p.landmark_name, p.landmark_distance, p.landmark_photo_path,
                    p.floor_level, p.frontage, p.parking_slots, p.utilities, p.amenities,
                    p.permitted_use, p.operating_hours, p.deposit_terms,
                    (SELECT GROUP_CONCAT(pp.photo_path ORDER BY pp.sort_order ASC, pp.id ASC SEPARATOR '|')
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
