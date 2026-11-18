<?php
namespace App\Core;

use App\Models\UserModel;
use App\Services\RateLimiter;

/**
 * Auth — website login state and role-based access control (RBAC).
 *
 * Roles:  superadmin | lessor | lessee
 * Status: pending | active | suspended
 */
final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /** Send visitors who aren't logged in to the login page. */
    public static function requireLogin(): void
    {
        if (!self::check() || !self::passwordStillCurrent()) {
            header('Location: /login.php');
            exit;
        }
    }

    /**
     * Logs out sessions that were opened with an old password (e.g. after "Forgot password").
     * The session keeps a short fingerprint of the password hash; if the hash in the database
     * changed, this session is ended. If the database can't be reached, the session is kept.
     */
    private static function passwordStillCurrent(): bool
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }
        try {
            $stmt = Database::connection()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([(int) $_SESSION['user']['id']]);
            $hash = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[Auth] password check skipped: ' . $e->getMessage());
            return $checked = true;
        }
        if ($hash === false) {                       // account deleted
            self::logout();
            return $checked = false;
        }
        $fp = self::fingerprint((string) $hash);
        if (!isset($_SESSION['user']['pw_fp'])) {    // sessions from before this feature
            $_SESSION['user']['pw_fp'] = $fp;
            return $checked = true;
        }
        if (!hash_equals((string) $_SESSION['user']['pw_fp'], $fp)) {
            self::logout();
            return $checked = false;
        }
        return $checked = true;
    }

    private static function fingerprint(string $passwordHash): string
    {
        return substr(hash('sha256', 'pw-fp|' . $passwordHash), 0, 32);
    }

    /**
     * Only allow the listed roles. Pending/suspended accounts go to /pending.php.
     * Usage: Auth::requireRole(['lessor']);
     */
    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $user = self::user();

        if (!in_array($user['role'], $roles, true)) {
            // Lessees (mobile app users) have no web dashboard. Before, this redirected
            // to /dashboard.php again and again (endless loop) — now it shows a clear page.
            if (($user['role'] ?? '') === 'lessee') {
                View::forbidden('This area is for lessors and administrators. Tenants can use the Pwestora mobile app.');
            }
            header('Location: /dashboard.php');
            exit;
        }
        if ($user['status'] !== 'active') {
            header('Location: /pending.php');
            exit;
        }
    }

    /**
     * Checks email + password. Returns the user row or null.
     * Brute-force protection: 5 wrong passwords per email / 20 per IP every 15 minutes.
     * Returns the string 'locked' when too many attempts were made.
     */
    public static function attempt(string $email, string $password)
    {
        $limiter  = new RateLimiter(Database::connection());
        $emailKey = 'web-login:email:' . strtolower($email);
        $ipKey    = 'web-login:ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $window   = 15 * 60;

        if ($limiter->tooManyAttempts($emailKey, 5, $window) || $limiter->tooManyAttempts($ipKey, 20, $window)) {
            return 'locked';
        }

        $users = new UserModel(Database::connection());
        $user  = $users->findByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $limiter->hit($emailKey, $window);
            $limiter->hit($ipKey, $window);
            return null;
        }

        $limiter->clear($emailKey);

        // Upgrade old password hashes automatically when PHP's default gets stronger.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $users->updatePasswordHash((int) $user['id'], $user['password_hash']);
        }
        return $user;
    }

    /** Stores only the minimal, safe user info in the session. */
    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['user'] = [
            'id'      => $user['id'],
            'role'    => $user['role'],
            'status'  => $user['status'],
            'name'    => $user['full_name'],
            'company' => $user['company_name'],
            'email'   => $user['email'],
            'pw_fp'   => self::fingerprint((string) ($user['password_hash'] ?? '')),
        ];
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}