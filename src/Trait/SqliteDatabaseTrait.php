<?php

declare(strict_types=1);

namespace Contenir\Test\Trait;

use Contenir\Test\Database\SqliteDatabase;
use PDO;
use PDOException;
use RuntimeException;

/**
 * A fresh in-memory SQLite database for each test, holding the tables of a
 * MySQL schema dump such as a site's database/schema.sql.
 *
 * Call setUpSqliteDatabase() from setUp(). The database belongs to the test
 * alone and goes with its connection, so there is nothing to roll back.
 *
 *     protected function setUp(): void
 *     {
 *         $this->setUpSqliteDatabase(dirname(__DIR__, 2) . '/database/schema.sql');
 *     }
 *
 * @api
 */
trait SqliteDatabaseTrait
{
    private PDO $sqlite;

    /**
     * Inserts $row into $table and returns the row's id.
     *
     * @param array<string, scalar|null> $row
     *
     * @throws PDOException When SQLite refuses the row.
     */
    protected function insertRow(string $table, array $row): int
    {
        return SqliteDatabase::insert($this->sqlite, $table, $row);
    }

    /**
     * @throws RuntimeException When $mysqlSchemaFile isn't a readable file.
     * @throws PDOException When SQLite refuses a statement of the translated schema.
     */
    protected function setUpSqliteDatabase(?string $mysqlSchemaFile = null): void
    {
        $this->sqlite = null === $mysqlSchemaFile
            ? SqliteDatabase::inMemory()
            : SqliteDatabase::fromMysqlFile($mysqlSchemaFile);
    }
}
