<?php
namespace App\Models;

use PDO;
/**
 * app/Models/NotifyModel.php
 * Handles database operations for sending Notifications.
 */
class NotifyModel extends Model
{

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

    /** Notices sent by any sender (lessor broadcasts use this too). */
    public function sentBy(int $senderId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications_log WHERE sender_id = ? ORDER BY sent_at DESC LIMIT ?');
        $stmt->bindValue(1, $senderId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Bell notifications for a lessor (newest first). */
    public function inboxFor(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT nl.*, nr.read_at FROM notification_recipients nr
             JOIN notifications_log nl ON nl.id = nr.notification_id
             WHERE nr.user_id = ? ORDER BY nl.sent_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->value('SELECT COUNT(*) FROM notification_recipients WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public function markAllRead(int $userId): void
    {
        $this->run('UPDATE notification_recipients SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $this->run('UPDATE notification_recipients SET read_at = NOW() WHERE notification_id = ? AND user_id = ?', [$notificationId, $userId]);
    }
}
