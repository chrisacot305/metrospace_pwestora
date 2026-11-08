<?php
namespace App\Core;

/**
 * Csrf — protects every form that changes data.
 * Each session gets a random token; every POST must send it back
 * (hidden field "csrf_token" or header "X-CSRF-Token").
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Hidden input ready to paste into a <form>. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function isValid(?string $sent): bool
    {
        $expected = $_SESSION['csrf_token'] ?? '';
        return is_string($sent) && $expected !== '' && hash_equals($expected, $sent);
    }

    /**
     * Stops the request with 403 if the token is missing or wrong.
     * (The old code used 419, which Apache/LiteSpeed don't know and turn into "500 Internal Server Error".)
     */
    public static function check(): void
    {
        $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::isValid(is_string($sent) ? $sent : null)) {
            http_response_code(403);
            die('Session expired. Please go back and try again.');
        }
    }
}
