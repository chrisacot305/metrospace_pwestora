<?php
/**
 * models/UserModel.php
 * Handles database operations for User Management.
 */
class UserModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

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
}