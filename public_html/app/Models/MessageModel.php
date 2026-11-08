<?php
namespace App\Models;

/** Tables: message_threads, messages (lessor ↔ tenant chat) */
class MessageModel extends Model
{
    /** Get (or lazily create) the thread between this lessor and a tenant. */
    public function findOrCreateThread(int $lessorId, int $tenantId): int
    {
        $id = $this->value('SELECT id FROM message_threads WHERE lessor_id = ? AND tenant_id = ?', [$lessorId, $tenantId]);
        if ($id !== false) {
            return (int) $id;
        }
        return $this->insert('INSERT INTO message_threads (lessor_id, tenant_id) VALUES (?, ?)', [$lessorId, $tenantId]);
    }

    /** $noticeType null = normal chat message. */
    public function addFromLessor(int $threadId, string $body, ?string $noticeType = null): void
    {
        if ($noticeType === null) {
            $this->run("INSERT INTO messages (thread_id, sender, body) VALUES (?, 'lessor', ?)", [$threadId, $body]);
        } else {
            $this->run("INSERT INTO messages (thread_id, sender, notice_type, body) VALUES (?, 'lessor', ?, ?)", [$threadId, $noticeType, $body]);
        }
    }

    public function conversation(int $lessorId, int $tenantId): array
    {
        return $this->all(
            'SELECT m.* FROM messages m
             JOIN message_threads mt ON mt.id = m.thread_id
             WHERE mt.lessor_id = ? AND mt.tenant_id = ?
             ORDER BY m.sent_at ASC',
            [$lessorId, $tenantId]
        );
    }
}
