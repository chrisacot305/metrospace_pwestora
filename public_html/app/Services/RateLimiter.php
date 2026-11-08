<?php
namespace App\Services;

use PDO;
use PDOException;

/**
 * RateLimiter — counts attempts per key inside a time window (table: rate_limits).
 * Used for website login. If the table doesn't exist yet (tools/migrate.php not run),
 * it logs a warning and lets the request through instead of breaking login.
 */
final class RateLimiter
{
    public function __construct(private PDO $pdo)
    {
    }

    public function tooManyAttempts(string $key, int $max, int $windowSeconds): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT hits FROM rate_limits WHERE rl_key = ? AND window_start > (NOW() - INTERVAL ? SECOND)'
            );
            $stmt->execute([hash('sha256', $key), $windowSeconds]);
            return (int) $stmt->fetchColumn() >= $max;
        } catch (PDOException $e) {
            error_log('[RateLimiter] ' . $e->getMessage() . ' — run tools/migrate.php');
            return false;
        }
    }

    public function hit(string $key, int $windowSeconds): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                    hits         = IF(window_start <= NOW() - INTERVAL ? SECOND, 1, hits + 1),
                    window_start = IF(window_start <= NOW() - INTERVAL ? SECOND, NOW(), window_start)'
            )->execute([hash('sha256', $key), $windowSeconds, $windowSeconds]);
        } catch (PDOException $e) {
            error_log('[RateLimiter] ' . $e->getMessage() . ' — run tools/migrate.php');
        }
    }

    public function clear(string $key): void
    {
        try {
            $this->pdo->prepare('DELETE FROM rate_limits WHERE rl_key = ?')->execute([hash('sha256', $key)]);
        } catch (PDOException $e) {
            error_log('[RateLimiter] ' . $e->getMessage());
        }
    }
}
