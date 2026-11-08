<?php
namespace App\Services;

/**
 * LeaseChecklist — the standard commercial-lease clauses (data lives in app/Config/lease_checklist.php).
 */
final class LeaseChecklist
{
    private static ?array $items = null;

    public static function items(): array
    {
        return self::$items ??= require APP_PATH . '/Config/lease_checklist.php';
    }
}
