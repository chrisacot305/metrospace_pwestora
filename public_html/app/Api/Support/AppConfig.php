<?php
namespace Api\Support;

/**
 * Reads optional settings (from .env through app/Config/constants.php).
 *
 *   API_DEMO_MODE=true   in .env  — ONLY for local demos. Default: off.
 *
 * Demo mode re-enables the old shortcuts (log in as the first lessee without a
 * token, match tenants by name, fall back to "any tenant"). Never turn it on
 * on a live server.
 */
final class AppConfig
{
    public static function demoMode(): bool
    {
        return defined('API_DEMO_MODE') && API_DEMO_MODE === true;
    }

    /** Turns a stored relative path ("uploads/x.jpg") into a full URL. */
    public static function publicUrl(string $relativePath): string
    {
        return APP_URL . '/' . ltrim($relativePath, '/');
    }
}
