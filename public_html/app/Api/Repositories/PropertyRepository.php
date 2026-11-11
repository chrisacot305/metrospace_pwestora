<?php
namespace Api\Repositories;

use PDOException;

/** Tables: properties, property_photos, saved_properties */
final class PropertyRepository extends Repository
{
    private const COVER_PHOTO_SUBQUERY =
        '(SELECT pp.photo_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.sort_order ASC, pp.id ASC LIMIT 1) AS cover_photo';

    /** Used by the older properties.php (optional type filter). */
    public function listVerifiedByType(?string $type): array
    {
        $sql = 'SELECT p.id, p.name, p.type, p.address, p.latitude, p.longitude, p.asking_rent, '
             . self::COVER_PHOTO_SUBQUERY . "
                FROM properties p
                WHERE p.status = 'verified'";
        $params = [];

        if ($type !== null && $type !== '' && $type !== 'All') {
            $sql .= ' AND p.type = ?';
            $params[] = $type;
        }

        return $this->fetchAll($sql, $params);
    }

    /** All verified listings with lessor name, newest first. */
    public function listVerifiedWithLessor(): array
    {
        try {
            return $this->fetchAll(
                'SELECT p.id, p.name, p.type, p.address, p.latitude, p.longitude, p.asking_rent,
                        p.floor_area, p.lease_term, p.escalation, p.occupancy_status,
                        p.total_units, p.available_units,
                        u.company_name AS lessor_name, ' . self::COVER_PHOTO_SUBQUERY . "
                 FROM properties p
                 JOIN users u ON u.id = p.lessor_id
                 WHERE p.status = 'verified'
                 ORDER BY p.created_at DESC"
            );
        } catch (PDOException $e) {
            // Older databases without the extended columns.
            return $this->fetchAll(
                'SELECT p.id, p.name, p.type, p.address, p.latitude, p.longitude, p.asking_rent,
                        u.company_name AS lessor_name, ' . self::COVER_PHOTO_SUBQUERY . "
                 FROM properties p
                 JOIN users u ON u.id = p.lessor_id
                 WHERE p.status = 'verified'
                 ORDER BY p.created_at DESC"
            );
        }
    }

    public function findVerifiedWithLessor(int $propertyId): ?array
    {
        return $this->fetchOne(
            "SELECT p.*,
                    u.company_name AS lessor_name, u.email AS lessor_email, u.phone AS lessor_phone
             FROM properties p
             JOIN users u ON u.id = p.lessor_id
             WHERE p.id = ? AND p.status = 'verified'
             LIMIT 1",
            [$propertyId]
        );
    }

    /** Minimal row (id, lessor_id) for a listing that accepts applications. */
    public function findVerifiedForApplication(int $propertyId): ?array
    {
        return $this->fetchOne(
            "SELECT id, lessor_id FROM properties WHERE id = ? AND status = 'verified'",
            [$propertyId]
        );
    }

    /** Photo paths, cover first. */
    public function photoPaths(int $propertyId): array
    {
        try {
            return $this->fetchColumnList(
                'SELECT photo_path FROM property_photos WHERE property_id = ? ORDER BY sort_order ASC, id ASC',
                [$propertyId]
            );
        } catch (PDOException $e) {
            // Before tools/migrate.php adds sort_order.
            return $this->fetchColumnList('SELECT photo_path FROM property_photos WHERE property_id = ? ORDER BY id ASC', [$propertyId]);
        }
    }

    /** Photo rows with category + caption, cover first (for the app's categorized gallery). */
    public function photoItems(int $propertyId): array
    {
        try {
            return $this->fetchAll(
                'SELECT photo_path, category, caption FROM property_photos WHERE property_id = ? ORDER BY sort_order ASC, id ASC',
                [$propertyId]
            );
        } catch (PDOException $e) {
            error_log('[API] property photo metadata unavailable (run tools/migrate.php): ' . $e->getMessage());
            return array_map(static fn($p) => ['photo_path' => $p, 'category' => null, 'caption' => null], $this->photoPaths($propertyId)); // id order
        }
    }

    public function savedPropertyIds(int $userId): array
    {
        try {
            return $this->fetchColumnList(
                'SELECT property_id FROM saved_properties WHERE user_id = ?',
                [$userId]
            );
        } catch (PDOException $e) {
            // Before tools/migrate.php creates saved_properties, logged-in users got a 500 error here.
            error_log('[API] saved_properties unavailable: ' . $e->getMessage());
            return [];
        }
    }

    public function isSaved(int $userId, int $propertyId): bool
    {
        try {
            return $this->fetchValue(
                'SELECT id FROM saved_properties WHERE user_id = ? AND property_id = ?',
                [$userId, $propertyId]
            ) !== false;
        } catch (PDOException $e) {
            error_log('[API] saved_properties unavailable: ' . $e->getMessage());
            return false;
        }
    }
}
