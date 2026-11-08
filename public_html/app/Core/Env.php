<?php
namespace App\Core;

/**
 * Env — reads the .env file (KEY=value lines) without needing Composer.
 *
 * Search order (first file found wins):
 *   1. one folder ABOVE public_html  (recommended on Hostinger — not reachable from the web)
 *   2. public_html/.env              (also blocked by .htaccess)
 */
final class Env
{
    private static array $values = [];
    private static ?string $loadedFrom = null;

    public static function load(array $candidateFiles): void
    {
        foreach ($candidateFiles as $file) {
            if (is_file($file) && is_readable($file)) {
                self::parse($file);
                self::$loadedFrom = $file;
                return;
            }
        }
    }

    /** Value of a setting: real environment variable first, then .env, then $default. */
    public static function get(string $key, $default = null)
    {
        $env = getenv($key);
        if ($env !== false) {
            return self::cast($env);
        }
        return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
    }

    public static function loadedFrom(): ?string
    {
        return self::$loadedFrom;
    }

    private static function parse(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (str_starts_with($key, 'export ')) {
                $key = trim(substr($key, 7));
            }
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $quoted = strlen($value) >= 2
                && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")));
            if ($quoted) {
                $value = substr($value, 1, -1);
                self::$values[$key] = $value; // quoted values are always strings
                continue;
            }
            // strip trailing " # comment"
            $hash = strpos($value, ' #');
            if ($hash !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
            self::$values[$key] = self::cast($value);
        }
    }

    private static function cast(string $value)
    {
        $lower = strtolower($value);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null' || $lower === '') return $lower === '' ? '' : null;
        return $value;
    }
}
