<?php
/**
 * app/init.php — application start-up (replaces the old config.php + includes/auth.php setup).
 *
 * Every page starts with:   require __DIR__ . '/app/init.php';
 * The mobile API starts with app/init_api.php (which loads this file first).
 *
 * What it does, in order:
 *   1. Loads settings from .env  (no passwords are stored in PHP files anymore)
 *   2. Defines the constants the code uses (DB_HOST, APP_URL, SMTP_*, ...)
 *   3. Registers the class autoloader  (App\... → app/...,  Api\... → app/Api/...)
 *   4. Sets up safe error logging
 *   5. Loads global helper functions (current_user(), csrf_token(), e(), ...)
 *   6. Opens the database connection ($pdo)
 *   7. Starts a secure session (website only)
 */

if (defined('APP_PATH')) {
    return; // already initialised
}

define('APP_PATH', __DIR__);              // .../public_html/app
define('APP_ROOT', dirname(__DIR__));     // .../public_html

// 1. Settings
require APP_PATH . '/Core/Env.php';
App\Core\Env::load([
    dirname(APP_ROOT) . '/.env',   // recommended: one folder above public_html
    APP_ROOT . '/.env',            // fallback: inside public_html (blocked by .htaccess)
]);

// 2. Constants
require APP_PATH . '/Config/constants.php';

// 3. Autoloader
spl_autoload_register(static function (string $class): void {
    $map = ['App\\' => APP_PATH . '/', 'Api\\' => APP_PATH . '/Api/'];
    foreach ($map as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

// 4. Errors
App\Core\ErrorHandler::register();
date_default_timezone_set((string) App\Core\Env::get('APP_TIMEZONE', date_default_timezone_get()));

// 5. Global helpers (same function names the pages have always used)
require APP_PATH . '/Helpers/functions.php';

// 6. Database
$pdo = App\Core\Database::connection();

// 7. Session + security headers (website pages only — the API and CLI tools don't use sessions)
if (!defined('APP_NO_SESSION') && PHP_SAPI !== 'cli') {
    App\Core\Session::start();
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    }
}
