<?php
/**
 * models/NotifyModel.php
 * Handles database operations for sending Notifications.
 */
class NotifyModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getActiveLessors(): array {
        return $this->pdo->query(
            "SELECT id, company_name FROM users WHERE role = 'lessor' AND status = 'active' ORDER BY company_name"
        )->fetchAll();
    }

    public function logNotification(int $senderId, string $audience, string $type, string $body): int {
        $stmt = $this->pdo->prepare('INSERT INTO notifications_log (sender_id, audience, title, body) VALUES (?, ?, ?, ?)');
        $stmt->execute([$senderId, $audience, $type, $body]);
        return (int) $this->pdo->lastInsertId();
    }

    public function addRecipients(int $notificationId, array $recipientIds): void {
        $stmt = $this->pdo->prepare('INSERT INTO notification_recipients (notification_id, user_id) VALUES (?, ?)');
        foreach ($recipientIds as $rid) {
            $stmt->execute([$notificationId, $rid]);
        }
    }

    public function getSentLogs(int $senderId, int $limit = 30): array {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications_log WHERE sender_id = ? ORDER BY sent_at DESC LIMIT ' . $limit);
        $stmt->execute([$senderId]);
        return $stmt->fetchAll();
    }
}