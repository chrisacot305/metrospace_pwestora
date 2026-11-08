<?php
/**
 * app/init_api.php — start-up for the mobile app API (replaces the old API start-up file that lived in api/).
 *
 * Every file in /api/ starts with:   require __DIR__ . '/../app/init_api.php';
 *
 * It loads the shared app/init.php (settings, autoloader, $pdo) WITHOUT a browser
 * session, then adds what the API needs:
 *   - JSON + CORS + security headers and the OPTIONS pre-flight reply
 *   - a global error handler, so a crash never leaks SQL or file paths to the app
 *   - the helper functions every endpoint uses: json_ok(), json_error(), json_body(),
 *     require_lessee_auth(), generate_api_token()
 *
 * Layering:  api/*.php (endpoint)  →  app/Api/Services  →  app/Api/Repositories (ALL SQL)
 */

define('APP_NO_SESSION', true);
require_once __DIR__ . '/init.php';

use Api\Http\Request;
use Api\Http\Response;
use Api\Repositories\UserRepository;
use Api\Services\AuthService;
use Api\Support\Token;

Response::sendHeaders();

if (Request::method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Never show internal error details (SQL, paths, stack traces) to the client.
set_exception_handler(static function (Throwable $e): void {
    error_log('[API] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Something went wrong on the server. Please try again later.', 500);
});

// ---------------------------------------------------------------------------
// Helper functions kept with the same names the endpoints have always used.
// ---------------------------------------------------------------------------

function json_ok($data = [], int $status = 200): void
{
    Response::ok($data, $status);
}

function json_error(string $message, int $status = 400): void
{
    Response::error($message, $status);
}

/** Reads the JSON body sent by the app, e.g. {"email":"...","password":"..."} */
function json_body(): array
{
    return Request::json();
}

/**
 * Requires a valid `Authorization: Bearer <token>` header for a lessee account.
 * Returns the user row on success, or sends a 401 JSON error and exits.
 * $allowDemoFallback only works when API_DEMO_MODE=true in .env (never on the live server).
 */
function require_lessee_auth(PDO $pdo, bool $allowDemoFallback = false): array
{
    return (new AuthService(new UserRepository($pdo)))->requireLessee($allowDemoFallback);
}

function generate_api_token(): string
{
    return Token::generate();
}
