<?php
namespace Api\Support;

/**
 * API login tokens.
 * The app receives the plain token once (at login/register). The database only
 * stores its SHA-256 hash, so a leaked database dump cannot be used to log in.
 */
final class Token
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(32)); // 64-character token
    }

    public static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken); // also 64 characters, fits the same column
    }
}
