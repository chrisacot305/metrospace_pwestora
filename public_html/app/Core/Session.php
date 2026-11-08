<?php
namespace App\Core;

/**
 * Session — starts PHP sessions with secure cookie settings.
 *  - HttpOnly: JavaScript can't read the session cookie (blocks cookie theft via XSS)
 *  - Secure:   cookie only sent over HTTPS (when the site is on HTTPS)
 *  - SameSite=Lax: cookie not sent on cross-site form posts (extra CSRF protection)
 *  - strict mode: PHP refuses session IDs it didn't create (blocks session fixation)
 *  - idle timeout: logged out after SESSION_LIFETIME seconds of inactivity
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) max(SESSION_LIFETIME, 1440));

        session_name('PWESTORA_SID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Idle timeout
        $now = time();
        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > SESSION_LIFETIME) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last_activity'] = $now;
    }

    /** New session ID after login — an attacker who planted an old ID can't reuse it. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
        session_destroy();
    }
}
