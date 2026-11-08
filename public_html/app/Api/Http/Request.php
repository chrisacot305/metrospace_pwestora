<?php
namespace Api\Http;

/**
 * Reads and cleans incoming request data.
 * Endpoints should get input through here instead of touching $_GET/$_POST directly.
 */
final class Request
{
    private static ?array $jsonCache = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Stops with 405 if the request is not the expected HTTP method. */
    public static function requireMethod(string $expected): void
    {
        if (self::method() !== $expected) {
            Response::error("Use $expected.", 405);
        }
    }

    /** Decoded JSON body (empty array if the body is missing or not valid JSON). */
    public static function json(): array
    {
        if (self::$jsonCache === null) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw === false ? '' : $raw, true);
            self::$jsonCache = is_array($decoded) ? $decoded : [];
        }
        return self::$jsonCache;
    }

    /** For multipart endpoints: form-data field first, then the JSON body. */
    public static function input(string $key, $default = null)
    {
        return $_POST[$key] ?? self::json()[$key] ?? $default;
    }

    /** Same as input() but always a trimmed string of at most $maxLength characters. */
    public static function inputString(string $key, int $maxLength = 255): string
    {
        return self::string(['v' => self::input($key, '')], 'v', $maxLength);
    }

    /**
     * Returns a trimmed string from $source[$key], cut to $maxLength characters.
     * Arrays/objects sent where a string is expected become '' instead of crashing.
     */
    public static function string(array $source, string $key, int $maxLength = 255, string $default = ''): string
    {
        $value = $source[$key] ?? $default;
        if (!is_scalar($value)) {
            return $default;
        }
        $value = trim((string) $value);
        return mb_substr($value, 0, $maxLength);
    }

    /** Positive integer or 0 if the value is missing/invalid. */
    public static function positiveInt($value): int
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return 0;
        }
        $int = (int) $value;
        return $int > 0 ? $int : 0;
    }

    /** Positive float or 0.0 */
    public static function positiveFloat($value): float
    {
        if (!is_scalar($value) || !is_numeric($value)) {
            return 0.0;
        }
        $float = (float) $value;
        return ($float > 0 && is_finite($float)) ? $float : 0.0;
    }

    /** The token from an `Authorization: Bearer <token>` header, or null. */
    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!$header && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if ($header && preg_match('/^Bearer\s+([A-Za-z0-9]{20,128})$/i', trim($header), $m)) {
            return $m[1];
        }
        return null;
    }

    /** Caller's IP (REMOTE_ADDR only — X-Forwarded-For can be faked by the client). */
    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
