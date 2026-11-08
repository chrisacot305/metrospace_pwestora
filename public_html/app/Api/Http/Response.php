<?php
namespace Api\Http;

/**
 * Sends JSON responses. Every endpoint replies through this class so the
 * format is always the same:  { "ok": true, ... }  or  { "ok": false, "error": "..." }
 */
final class Response
{
    public static function sendHeaders(): void
    {
        $origin = defined('API_ALLOWED_ORIGIN') ? API_ALLOWED_ORIGIN : '*';

        header('Content-Type: application/json; charset=utf-8');
        // Mobile apps (and Flutter's own dev web server) call this from a different origin.
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        // Basic hardening headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
    }

    /** Success response. Same shape the API always returned: ['ok' => true] + $data */
    public static function ok($data = [], int $status = 200): void
    {
        self::send(['ok' => true] + (is_array($data) ? $data : ['data' => $data]), $status);
    }

    /** Error response: { "ok": false, "error": "..." } */
    public static function error(string $message, int $status = 400): void
    {
        self::send(['ok' => false, 'error' => $message], $status);
    }

    /** Raw payload (used by the older endpoints that reply with "success"/"message"). */
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }
}
