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
        if (!self::check()) {
            header('Location: /login.php');
            exit;
        }
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
            $users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
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
        ];
    }

    public static function logout(): void
    {
        Session::destroy();
    }
}
