<?php
namespace Api\Repositories;

/** Table: users */
final class UserRepository extends Repository
{
    public function findLesseeByTokenHash(string $tokenHash): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM users WHERE api_token = ? AND role = 'lessee' LIMIT 1",
            [$tokenHash]
        );
    }

    /** Demo mode only. */
    public function findFirstActiveLessee(): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM users WHERE role = 'lessee' AND status = 'active' ORDER BY id ASC LIMIT 1"
        );
    }

    public function findLesseeByEmail(string $email): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM users WHERE email = ? AND role = 'lessee' LIMIT 1",
            [$email]
        );
    }

    /** True if another account already uses this email ($exceptUserId = your own id when editing). */
    public function emailExists(string $email, int $exceptUserId = 0): bool
    {
        return $this->fetchValue(
            'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1',
            [$email, $exceptUserId]
        ) !== false;
    }

    public function phoneExists(string $phone, string $normalizedPhone): bool
    {
        return $this->fetchValue(
            'SELECT id FROM users WHERE phone = ? OR phone = ? LIMIT 1',
            [$phone, $normalizedPhone]
        ) !== false;
    }

    /** Lessees don't go through document verification like lessors do — active immediately. */
    public function createLessee(string $fullName, string $email, string $phone, string $passwordHash, ?string $tokenHash): int
    {
        return $this->insert(
            "INSERT INTO users (role, status, full_name, email, phone, password_hash, api_token)
             VALUES ('lessee', 'active', ?, ?, ?, ?, ?)",
            [$fullName, $email, $phone, $passwordHash, $tokenHash]
        );
    }

    public function updateApiToken(int $userId, ?string $tokenHash): void
    {
        $this->execute('UPDATE users SET api_token = ? WHERE id = ?', [$tokenHash, $userId]);
    }

    public function updateProfile(int $userId, string $fullName, string $email, string $phone): void
    {
        $this->execute(
            'UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?',
            [$fullName, $email, $phone, $userId]
        );
    }
}
