<?php
namespace App\Models;

use PDO;

/**
 * PasswordResetModel — one-time "forgot password" links (table: password_resets).
 *
 * Security notes:
 *  - The raw token is only ever in the email link. The database stores its SHA-256 hash,
 *    so someone who reads the database (backup, SQL leak) cannot use the links.
 *  - Tokens expire after TOKEN_MINUTES and can be used once.
 *  - Asking for a new link cancels every older unused link for that account.
 */
class PasswordResetModel extends Model
{
    public const TOKEN_MINUTES = 30;

    private static bool $tableReady = false;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->ensureTable();
    }

    /** Creates the table on first use, so the feature works even before tools/migrate.php is run. */
    private function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            request_ip VARCHAR(45) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_token (token_hash),
            KEY idx_user (user_id),
            KEY idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        self::$tableReady = true;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** A raw token looks like 64 lowercase hex characters. Anything else is rejected before touching the DB. */
    public static function looksValid(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    /**
     * Creates a new link for the user and returns the RAW token (to put in the email).
     * Older unused links for the same user stop working.
     */
    public function create(int $userId, string $ip): string
    {
        $token = bin2hex(random_bytes(32)); // 256 bits of randomness

        $this->pdo->beginTransaction();
        try {
            $this->run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
            $this->run(
                'INSERT INTO password_resets (user_id, token_hash, expires_at, request_ip)
                 VALUES (?, ?, NOW() + INTERVAL ' . self::TOKEN_MINUTES . ' MINUTE, ?)',
                [$userId, self::hashToken($token), mb_substr($ip, 0, 45)]
            );
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->purgeOld();
        return $token;
    }

    /** Returns the reset row joined with its user, or null if the token is unknown, used, or expired. */
    public function findValid(string $token): ?array
    {
        if (!self::looksValid($token)) {
            return null;
        }
        return $this->one(
            'SELECT pr.id AS reset_id, pr.user_id, u.email, u.full_name, u.status, u.password_hash
               FROM password_resets pr
               JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = ?
                AND pr.used_at IS NULL
                AND pr.expires_at > NOW()
              LIMIT 1',
            [self::hashToken($token)]
        );
    }

    /**
     * Uses the token and sets the new password in ONE transaction.
     * The "used_at IS NULL" check makes this safe against double-submits / two tabs:
     * only one request can ever win. Returns false if the token was already used or expired.
     */
    public function consumeAndSetPassword(string $token, int $userId, string $newHash): bool
    {
        $this->pdo->beginTransaction();
        try {
            $claimed = $this->run(
                'UPDATE password_resets SET used_at = NOW()
                  WHERE token_hash = ? AND user_id = ? AND used_at IS NULL AND expires_at > NOW()',
                [self::hashToken($token), $userId]
            );
            if ($claimed !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            // New password; also log out the mobile app (api_token) and drop any pending email code.
            $this->run(
                'UPDATE users SET password_hash = ?, api_token = NULL, otp_code = NULL, otp_expires_at = NULL WHERE id = ?',
                [$newHash, $userId]
            );
            // Any other open reset links for this account stop working too.
            $this->run('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Housekeeping: forget links that expired more than a day ago. */
    private function purgeOld(): void
    {
        try {
            $this->run('DELETE FROM password_resets WHERE expires_at < NOW() - INTERVAL 1 DAY');
        } catch (\Throwable $e) {
            error_log('[PasswordReset purge] ' . $e->getMessage());
        }
    }
}
