<?php
namespace App\Models;

use PDO;
/**
 * app/Models/UserModel.php
 * Handles database operations for User Management.
 */
class UserModel extends Model
{

    public function getAllUsers(): array {
        return $this->pdo->query(
            "SELECT * FROM users WHERE role IN ('lessor', 'lessee') ORDER BY created_at DESC"
        )->fetchAll();
    }

    public function getUserById(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? AND role IN ("lessor", "lessee")');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateUserStatus(int $id, string $status): void {
        $stmt = $this->pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
    }

    public function findById(int $id): ?array
    {
        return $this->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function emailExists(string $email): bool
    {
        return $this->value('SELECT id FROM users WHERE email = ?', [$email]) !== false;
    }

    /** New lessor accounts start as "pending" until SuperAdmin verifies their documents. */
    public function createLessor(string $company, string $name, string $email, string $phone, string $passwordHash): int
    {
        return $this->insert(
            "INSERT INTO users (role, status, company_name, full_name, email, phone, password_hash)
             VALUES ('lessor', 'pending', ?, ?, ?, ?, ?)",
            [$company, $name, $email, $phone, $passwordHash]
        );
    }

    public function setOtp(int $userId, ?string $code, ?string $expiresAt): void
    {
        $this->run('UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?', [$code, $expiresAt, $userId]);
    }

    public function updatePasswordHash(int $userId, string $hash): void
    {
        $this->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $userId]);
    }

    public function updateContact(int $userId, string $email, string $phone): void
    {
        $this->run('UPDATE users SET email = ?, phone = ? WHERE id = ?', [$email, $phone, $userId]);
    }
}
