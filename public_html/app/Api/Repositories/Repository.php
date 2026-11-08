<?php
namespace Api\Repositories;

use PDO;

/**
 * Base class for all repositories.
 * A repository is the ONLY place that talks to the database. Every query uses
 * prepared statements with "?" placeholders, so user input is never pasted into SQL.
 */
abstract class Repository
{
    public function __construct(protected PDO $pdo)
    {
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** First column of the first row, or false if there is no row. */
    protected function fetchValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** First column of every row, as a flat list. */
    protected function fetchColumnList(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Runs INSERT/UPDATE/DELETE and returns the number of affected rows. */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Runs an INSERT and returns the new row id. */
    protected function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    /** "?, ?, ?" for an IN (...) list. */
    protected static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
