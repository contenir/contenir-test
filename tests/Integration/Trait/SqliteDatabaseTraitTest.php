<?php

declare(strict_types=1);

namespace Contenir\Test\Tests\Integration\Trait;

use Contenir\Test\Trait\SqliteDatabaseTrait;
use Contenir\Test\Trait\TemporaryDirectoryTrait;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class SqliteDatabaseTraitTest extends TestCase
{
    use SqliteDatabaseTrait;
    use TemporaryDirectoryTrait;

    #[Test]
    public function aSchemaFilesTablesTakeRows(): void
    {
        $this->setUpSqliteDatabase($this->writeTemporaryFile(
            'schema.sql',
            'CREATE TABLE `page` (`page_id` int NOT NULL AUTO_INCREMENT, `title` varchar(9), PRIMARY KEY (`page_id`));',
        ));

        $id = $this->insertRow('page', ['title' => 'Home']);

        static::assertSame(
            [1, [['page_id' => 1, 'title' => 'Home']]],
            [$id, $this->sqlite->query('SELECT * FROM page')?->fetchAll()],
        );
    }

    #[Test]
    public function withoutASchemaTheDatabaseIsEmpty(): void
    {
        $this->setUpSqliteDatabase();

        static::assertSame(
            [],
            $this->sqlite->query("SELECT name FROM sqlite_master WHERE type = 'table'")?->fetchAll(PDO::FETCH_COLUMN),
        );
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
