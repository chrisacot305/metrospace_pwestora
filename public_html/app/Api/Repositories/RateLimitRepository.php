<?php
namespace Api\Repositories;

/** Table: rate_limits */
final class RateLimitRepository extends Repository
{
    public function hitsInWindow(string $key, int $windowSeconds): int
    {
        $hits = $this->fetchValue(
            'SELECT hits FROM rate_limits
             WHERE rl_key = ? AND window_start > (NOW() - INTERVAL ? SECOND)',
            [self::hashKey($key), $windowSeconds]
        );
        return $hits === false ? 0 : (int) $hits;
    }

    /** Adds one attempt; starts a new window if the old one has expired. */
    public function hit(string $key, int $windowSeconds): void
    {
        $this->execute(
            'INSERT INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
                hits         = IF(window_start <= NOW() - INTERVAL ? SECOND, 1, hits + 1),
                window_start = IF(window_start <= NOW() - INTERVAL ? SECOND, NOW(), window_start)',
            [self::hashKey($key), $windowSeconds, $windowSeconds]
        );
    }

    public function clear(string $key): void
    {
        $this->execute('DELETE FROM rate_limits WHERE rl_key = ?', [self::hashKey($key)]);
    }

    /** Keys contain emails/phones — store a hash, not the raw value. */
    private static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
