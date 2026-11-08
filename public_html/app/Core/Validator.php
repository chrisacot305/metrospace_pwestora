<?php
namespace App\Core;

/**
 * Validator — small helpers for cleaning form input.
 */
final class Validator
{
    /** Trimmed string from an input array, cut to $max characters. Arrays become ''. */
    public static function str(array $src, string $key, int $max = 255, string $default = ''): string
    {
        $v = $src[$key] ?? $default;
        if (!is_scalar($v)) {
            return $default;
        }
        return mb_substr(trim((string) $v), 0, $max);
    }

    public static function int(array $src, string $key, int $default = 0): int
    {
        $v = $src[$key] ?? $default;
        return (is_scalar($v) && is_numeric($v)) ? (int) $v : $default;
    }

    /** Money/number field that may contain commas ("12,500.50"). */
    public static function money(array $src, string $key): float
    {
        $v = $src[$key] ?? '0';
        if (!is_scalar($v)) {
            return 0.0;
        }
        $v = str_replace([',', ' '], '', (string) $v);
        return is_numeric($v) ? (float) $v : 0.0;
    }

    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Y-m-d date string, or null if empty/invalid. */
    public static function date(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : null;
    }

    /** Integer list (e.g. checkboxes "recipient_ids[]"). */
    public static function intList($value): array
    {
        return is_array($value) ? array_values(array_map('intval', array_filter($value, 'is_scalar'))) : [];
    }
}
