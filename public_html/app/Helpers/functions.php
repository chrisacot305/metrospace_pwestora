<?php
/**
 * app/Helpers/functions.php — global helper functions.
 *
 * These keep the SAME names the old includes/auth.php provided, so views and
 * older code keep working. Each one now just calls the proper class.
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\AuditModel;

// ---------------------------------------------------------------- auth / RBAC
function current_user(): ?array            { return Auth::user(); }
function is_logged_in(): bool              { return Auth::check(); }
function require_login(): void            { Auth::requireLogin(); }
function require_role(array $roles): void  { Auth::requireRole($roles); }
function log_user_in(array $user): void    { Auth::login($user); }
function log_user_out(): void              { Auth::logout(); }

// ---------------------------------------------------------------- CSRF
function csrf_token(): string { return Csrf::token(); }
function csrf_field(): string { return Csrf::field(); }
function csrf_check(): void   { Csrf::check(); }

// ---------------------------------------------------------------- audit trail
/** Simple audit trail — call this after important actions. */
function audit_log(PDO $pdo, ?int $actorId, string $action, string $details = ''): void
{
    (new AuditModel($pdo))->log($actorId, $action, $details);
}

// ---------------------------------------------------------------- output escaping (XSS prevention)
/** Escape any value for safe HTML output:  <?= e($name) ?> */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Escape a value for use inside a <script> block or an HTML attribute holding JSON. */
function js($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

/** Link to an uploaded file. Lessor KYC documents go through the protected viewer. */
function upload_url(?string $path): string
{
    $path = ltrim((string) $path, '/');
    if (str_starts_with($path, 'uploads/verification/')) {
        return '/view_document.php?file=' . rawurlencode($path);
    }
    return '/' . $path;
}

require __DIR__ . '/view_helpers.php';
