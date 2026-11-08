<?php
namespace App\Core;

/**
 * ErrorHandler — errors are written to app/storage/logs/php-error.log
 * (NOT the public error_log file in public_html) and never shown to
 * visitors unless APP_DEBUG=true in .env.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        $logDir = APP_PATH . '/storage/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            ini_set('error_log', $logDir . '/php-error.log');
        }
        ini_set('log_errors', '1');
        ini_set('display_errors', APP_DEBUG ? '1' : '0');
        error_reporting(E_ALL);
    }
}
