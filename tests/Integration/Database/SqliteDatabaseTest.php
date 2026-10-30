<?php

declare(strict_types=1);

namespace Contenir\Test\Tests\Integration\Database;

use Contenir\Test\Database\SqliteDatabase;
use Contenir\Test\Trait\TemporaryDirectoryTrait;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[Group('integration')]
final class SqliteDatabaseTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private const string SCHEMA = <<<'SQL'
        SET NAMES utf8mb4;

        -- The site's pages.
        CREATE TABLE IF NOT EXISTS `resource` (
          `resource_id` int unsigned NOT NULL AUTO_INCREMENT,
          `slug`        varchar(191) DEFAULT NULL,
          `active`      enum('active','inactive') DEFAULT 'active',
          PRIMARY KEY (`resource_id`),
          UNIQUE KEY `slug` (`slug`),
          KEY `active` (`active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;

    /**
     * @return list<string>
     */
    private static function tables(PDO $pdo): array
    {
        /** @var list<string> */
        return (
            $pdo->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
            )
                ?->fetchAll(PDO::FETCH_COLUMN) ?? []
        );
    }

    #[Test]
    public function aDirectoryIsNotASchemaFile(): void
    {
        $this->expectException(RuntimeException::class);

        SqliteDatabase::fromMysqlFile($this->temporaryPath());
    }

    #[Test]
    public function aMissingSchemaFileIsAnError(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('No schema file at "%s".', $this->temporaryPath('missing.sql')));

        SqliteDatabase::fromMysqlFile($this->temporaryPath('missing.sql'));
    }

    #[Test]
    public function aMysqlSchemaFileGivesItsTables(): void
    {
        $file = $this->writeTemporaryFile('database/schema.sql', self::SCHEMA);

        static::assertSame(['resource'], self::tables(SqliteDatabase::fromMysqlFile($file)));
    }

    #[Test]
    public function aMysqlSchemaGivesItsTables(): void
    {
        static::assertSame(['resource'], self::tables(SqliteDatabase::fromMysql(self::SCHEMA)));
    }

    #[Test]
    public function anInMemoryDatabaseIsEmptyThrowsAndFetchesByColumn(): void
    {
        $pdo = SqliteDatabase::inMemory();

        static::assertSame(
            [[], PDO::ERRMODE_EXCEPTION, PDO::FETCH_ASSOC],
            [
                self::tables($pdo),
                $pdo->getAttribute(PDO::ATTR_ERRMODE),
                $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE),
            ],
        );
    }

    #[Test]
    public function eachInMemoryDatabaseIsItsOwn(): void
    {
        SqliteDatabase::fromMysql(self::SCHEMA);

        static::assertSame([], self::tables(SqliteDatabase::inMemory()));
    }

    #[Test]
    public function insertingARowGivesItsIdAndKeepsItsDefaults(): void
    {
        $pdo = SqliteDatabase::fromMysql(self::SCHEMA);

        $first  = SqliteDatabase::insert($pdo, 'resource', ['slug' => 'home']);
        $second = SqliteDatabase::insert($pdo, 'resource', ['slug' => 'about', 'active' => null]);

        static::assertSame(
            [
                1,
                2,
                [
                    ['resource_id' => 1, 'slug' => 'home', 'active' => 'active'],
                    ['resource_id' => 2, 'slug' => 'about', 'active' => null],
                ],
            ],
            [$first, $second, $pdo->query('SELECT * FROM resource ORDER BY resource_id')?->fetchAll()],
        );
    }

    #[Test]
    public function theTranslatedUniqueKeyHolds(): void
    {
        $pdo = SqliteDatabase::fromMysql(self::SCHEMA);
        SqliteDatabase::insert($pdo, 'resource', ['slug' => 'home']);

        $this->expectException(PDOException::class);

        SqliteDatabase::insert($pdo, 'resource', ['slug' => 'home']);
    }

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }
}
