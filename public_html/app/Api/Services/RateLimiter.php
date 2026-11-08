<?php
namespace Api\Services;

use Api\Repositories\RateLimitRepository;
use PDOException;

/**
 * Limits how often something can be tried (login, sending OTP, guessing OTP).
 * Stops password guessing and SMS spam.
 *
 * If the rate_limits table has not been created yet (tools/migrate.php
 * not run), it logs a warning and lets the request through instead of breaking login.
 */
final class RateLimiter
{
    public function __construct(private RateLimitRepository $repo)
    {
    }

    public function tooManyAttempts(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        try {
            return $this->repo->hitsInWindow($key, $windowSeconds) >= $maxAttempts;
        } catch (PDOException $e) {
            error_log('[API RateLimiter] ' . $e->getMessage() . ' — run tools/migrate.php');
            return false;
        }
    }

    public function hit(string $key, int $windowSeconds): void
    {
        try {
            $this->repo->hit($key, $windowSeconds);
        } catch (PDOException $e) {
            error_log('[API RateLimiter] ' . $e->getMessage() . ' — run tools/migrate.php');
        }
    }

    public function clear(string $key): void
    {
        try {
            $this->repo->clear($key);
        } catch (PDOException $e) {
            error_log('[API RateLimiter] ' . $e->getMessage());
        }
    }
}
