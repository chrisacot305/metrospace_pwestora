<?php
namespace Api\Repositories;

/** Tables: message_threads, messages */
final class MessageRepository extends Repository
{
    public function findThreadId(int $lessorId, int $tenantId): ?int
    {
        $id = $this->fetchValue(
            'SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?',
            [$lessorId, $tenantId]
        );
        return $id === false ? null : (int) $id;
    }

    public function createThread(int $lessorId, int $tenantId): int
    {
        return $this->insert(
            'INSERT INTO message_threads (lessor_id, tenant_id) VALUES (?, ?)',
            [$lessorId, $tenantId]
        );
    }

    public function findOrCreateThread(int $lessorId, int $tenantId): int
    {
        return $this->findThreadId($lessorId, $tenantId) ?? $this->createThread($lessorId, $tenantId);
    }

    /** $sender: 'tenant' | 'lessor'  (the only values the messages.sender column allows) */
    public function add(int $threadId, string $sender, string $body, ?string $noticeType = null): int
    {
        if ($noticeType === null) {
            return $this->insert(
                'INSERT INTO messages (thread_id, sender, body) VALUES (?, ?, ?)',
                [$threadId, $sender, $body]
            );
        }
        return $this->insert(
            'INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, ?, ?, ?)',
            [$threadId, $sender, $noticeType, $body]
        );
    }

    /** Oldest first, like the web chat view. */
    public function listForThread(int $threadId): array
    {
        return $this->fetchAll(
            'SELECT sender, notice_type, body, sent_at FROM messages WHERE thread_id = ? ORDER BY sent_at ASC',
            [$threadId]
        );
    }
}
