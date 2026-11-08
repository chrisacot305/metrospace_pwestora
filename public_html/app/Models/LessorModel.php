<?php
namespace App\Models;

use PDO;
/**
 * app/Models/LessorModel.php
 * Handles database operations for Lessor Verification.
 */
class LessorModel extends Model
{

    public function getLessorById(int $lessorId) {
        // FIXED: Now we join the lessor_profiles table to actually fetch the document paths!
        $stmt = $this->pdo->prepare('
            SELECT u.*, 
                   lp.business_permit_path, lp.sec_dti_path, lp.bir_cert_path, lp.land_title_path, lp.gov_id_path
            FROM users u
            LEFT JOIN lessor_profiles lp ON lp.user_id = u.id
            WHERE u.id = ? AND u.role = "lessor"
        ');
        $stmt->execute([$lessorId]);
        return $stmt->fetch();
    }

    public function approveLessor(int $lessorId, int $adminId): void {
        $this->pdo->prepare('UPDATE users SET status = "active" WHERE id = ? AND role = "lessor"')->execute([$lessorId]);
        // Update profile if it exists
        $this->pdo->prepare('UPDATE lessor_profiles SET verified_by = ?, verified_at = NOW() WHERE user_id = ?')->execute([$adminId, $lessorId]);
    }

    public function rejectLessor(int $lessorId): void {
        $this->pdo->prepare('UPDATE users SET status = "suspended" WHERE id = ? AND role = "lessor"')->execute([$lessorId]);
    }

    public function getAllLessors(): array {
        return $this->pdo->query(
            'SELECT u.*, 
                    lp.business_permit_path, lp.sec_dti_path, lp.bir_cert_path, lp.land_title_path, lp.gov_id_path,
                    (SELECT COUNT(*) FROM properties p WHERE p.lessor_id = u.id) AS property_count
             FROM users u
             LEFT JOIN lessor_profiles lp ON lp.user_id = u.id
             WHERE u.role = "lessor"
             ORDER BY CASE WHEN u.status = "pending" THEN 0 ELSE 1 END, u.created_at DESC'
        )->fetchAll();
    }
}