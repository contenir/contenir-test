<?php

declare(strict_types=1);

namespace Contenir\Test\Database;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

use function array_keys;
use function array_map;
use function assert;
use function file_get_contents;
use function implode;
use function is_file;
use function sprintf;

/**
 * In-memory SQLite databases for tests: empty, or holding a MySQL schema's tables.
 *
 * Each connection is its own database, gone when the connection is, so a test
 * that makes one in setUp() starts from nothing and leaves nothing behind.
 * Errors throw, rows fetch as arrays keyed by column, and foreign keys are not
 * enforced, as SQLite's default is, so a test can insert only the rows it needs.
 *
 * @api
 */
final class SqliteDatabase
{
    /**
     * An in-memory database holding the tables of a MySQL schema dump.
     *
     * @throws PDOException When SQLite refuses a statement of the translated schema.
     */
    public static function fromMysql(string $schema): PDO
    {
        $pdo = self::inMemory();
        $pdo->exec(SqliteSchema::fromMysql($schema));

        return $pdo;
    }

    /**
     * An in-memory database holding the tables of the MySQL schema dump in $file.
     *
     * @throws RuntimeException When $file isn't a readable file.
     * @throws PDOException When SQLite refuses a statement of the translated schema.
     */
    public static function fromMysqlFile(string $file): PDO
    {
        $schema = is_file($file) ? file_get_contents($file) : false;
        if (false === $schema) {
            throw new RuntimeException(sprintf('No schema file at "%s".', $file));
        }

        return self::fromMysql($schema);
    }

    /**
     * @throws PDOException When SQLite can't open a database.
     */
    public static function inMemory(): PDO
    {
        return new PDO('sqlite::memory:', options: [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Inserts $row into $table and returns the row's id.
     *
     * @param array<string, scalar|null> $row
     *
     * @throws PDOException When SQLite refuses the row.
     */
    public static function insert(PDO $pdo, string $table, array $row): int
    {
        $columns   = array_keys($row);
        $statement = $pdo->prepare(sprintf(
            'INSERT INTO "%s" ("%s") VALUES (%s)',
            $table,
            implode('", "', $columns),
            implode(', ', array_map(static fn(string $column): string => ":{$column}", $columns)),
        ));
        assert(
            $statement instanceof PDOStatement,
            description: 'PDO throws on errors, so prepare() gives a statement.',
        );
        $statement->execute($row);

        return (int) $pdo->lastInsertId();
    }
}
