<?php
namespace Api\Repositories;

/** Table: lease_checklist_acknowledgments */
final class ChecklistRepository extends Repository
{
    /** Saves all keys in one transaction (all or nothing). */
    public function acknowledgeAll(int $userId, array $itemKeys): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO lease_checklist_acknowledgments (user_id, item_key) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE acknowledged_at = NOW()'
            );
            foreach ($itemKeys as $key) {
                $stmt->execute([$userId, $key]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
