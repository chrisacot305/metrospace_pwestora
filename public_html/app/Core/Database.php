<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Database — one shared PDO connection.
 * Real (native) prepared statements are used everywhere, so user input is
 * never mixed into SQL text.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                // Never show raw database errors (they contain the DB user/host) to visitors.
                error_log('DB connection failed: ' . $e->getMessage());
                if (PHP_SAPI !== 'cli') {
                    http_response_code(503);
                }
                die('Something went wrong connecting to the database. Please try again later.');
            }
        }
        return self::$pdo;
    }
}
