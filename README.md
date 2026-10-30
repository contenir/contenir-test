# contenir/contenir-test

[![Continuous Integration](https://github.com/contenir/contenir-test/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-test/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-test/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-test)

Test helpers shared by Contenir packages and sites, in place of the copies each kept of its own:

- **`Container\ArrayContainer`**: a PSR-11 container over an array of services and factories.
- **`Clock\FixedClock`**: a PSR-20 clock stopped at one moment.
- **`Log\RecordingLogger`**: a PSR-3 logger that keeps every record for the test to read back.
- **`Trait\TemporaryDirectoryTrait`**: a scratch directory for each test, removed afterwards.
- **`Database\SqliteDatabase`** and **`Trait\SqliteDatabaseTrait`**: an in-memory SQLite database for each test,
  holding the tables of a MySQL schema dump such as a site's `database/schema.sql`.

It depends only on the PSR interfaces and `pdo_sqlite`, so packages and sites on any framework can use it.

## Install

```bash
composer require --dev contenir/contenir-test
```

## A container for factory tests

```php
use Contenir\Test\Container\ArrayContainer;

$container = new ArrayContainer(
    ['config' => ['site' => ['url' => 'https://example.test']]],
    [Mailer::class => static fn(ContainerInterface $c): Mailer => (new MailerFactory())($c)],
);

$container->get(Mailer::class);   // built once, then kept
$container->set(Clock::class, FixedClock::at('2026-10-30 09:00'));
```

A missing service throws `ServiceNotFoundException` (PSR-11's not-found exception), and a factory that throws is
wrapped in `ServiceFailedException`, with the factory's exception as the previous one.

## A clock and a logger

```php
$clock  = FixedClock::at('2026-10-30 09:00', 'Australia/Melbourne');
$logger = new RecordingLogger();

// ...

$logger->messages(LogLevel::ERROR);   // ['Could not send.']
$logger->records();                   // every record: level, message and context
```

## A scratch directory

```php
use Contenir\Test\Trait\TemporaryDirectoryTrait;

final class ExportTest extends TestCase
{
    use TemporaryDirectoryTrait;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    public function testWritesTheFile(): void
    {
        $source = $this->writeTemporaryFile('in/page.html', '<p>Hello</p>');
        // ...
        static::assertFileExists($this->temporaryPath('out/page.html'));
    }
}
```

The directory is removed even when a test leaves read-only directories or symlinks behind; symlinks are removed,
never followed. The umask is 022 while the test runs.

## A database from a site's schema

```php
use Contenir\Test\Trait\SqliteDatabaseTrait;

final class ContentRepositoryTest extends TestCase
{
    use SqliteDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpSqliteDatabase(dirname(__DIR__, 3) . '/database/schema.sql');
    }

    public function testFindsAPublishedPage(): void
    {
        $id = $this->insertRow('resource', ['slug' => 'about', 'title' => 'About', 'active' => 'active']);
        // query $this->sqlite, or hand it to the code under test
    }
}
```

Each test gets its own in-memory database, so there is nothing to roll back. Errors throw, rows fetch as arrays keyed
by column, and foreign keys are not enforced, so a test inserts only the rows it needs.

`SqliteSchema::fromMysql()` does the translation. MySQL's type names are kept, as SQLite accepts them; comments, `SET`
statements, table options, indexes, character sets, collations and column comments go; `AUTO_INCREMENT` becomes
`INTEGER PRIMARY KEY AUTOINCREMENT`; `enum(...)` becomes `TEXT`; unique keys and foreign keys stay. MySQL remains
the schema of record: this lets tests use its tables, and isn't a second schema to keep.

## Quality

```bash
composer check
```

runs Mago's formatting, lint and analysis, the unit and integration suites, and Infection, held at 100% coverage and
100% MSI.
