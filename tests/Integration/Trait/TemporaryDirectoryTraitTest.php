<?php

declare(strict_types=1);

namespace Contenir\Test\Tests\Integration\Trait;

use Contenir\Test\Trait\TemporaryDirectoryTrait;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function decoct;
use function dirname;
use function file_exists;
use function file_get_contents;
use function fileperms;
use function is_dir;
use function iterator_count;
use function mkdir;
use function preg_match;
use function preg_quote;
use function rmdir;
use function symlink;
use function sys_get_temp_dir;
use function touch;
use function umask;
use function uniqid;
use function unlink;

#[Group('integration')]
final class TemporaryDirectoryTraitTest extends TestCase
{
    use TemporaryDirectoryTrait;

    private int $umask;

    #[Test]
    public function aRelativePathIsInsideTheDirectory(): void
    {
        static::assertSame("{$this->temporaryPath()}/a/b.txt", $this->temporaryPath('a/b.txt'));
    }

    #[Test]
    public function filesAreCreatedUnderAUmaskOf022(): void
    {
        $path = $this->writeTemporaryFile('a/file.txt', '');

        static::assertSame(
            ['755', '755', '644'],
            [
                decoct(fileperms($this->temporaryPath()) & 0o777),
                decoct(fileperms(dirname($path)) & 0o777),
                decoct(fileperms($path) & 0o777),
            ],
        );
    }

    #[Test]
    public function tearingDownADirectoryAlreadyGoneOnlyRestoresTheUmask(): void
    {
        rmdir($this->temporaryPath());

        $this->tearDownTemporaryDirectory();

        static::assertSame(0o077, umask());
    }

    #[Test]
    public function tearingDownRemovesEverythingAndRestoresTheUmask(): void
    {
        $outside = $this->writeTemporaryFile('outside/kept.txt', 'kept');
        $this->writeTemporaryFile('locked/deep/file.txt', 'x');
        chmod($this->temporaryPath('locked/deep/file.txt'), permissions: 0o400);
        chmod($this->temporaryPath('locked/deep'), permissions: 0o500);
        chmod($this->temporaryPath('locked'), permissions: 0o500);
        mkdir($this->temporaryPath('linked'));
        symlink(dirname($outside), $this->temporaryPath('linked/directory'));
        symlink($outside, $this->temporaryPath('linked/file'));
        chmod($this->temporaryPath(), permissions: 0o500);

        $kept = sys_get_temp_dir() . '/contenir-test-kept-' . uniqid();
        mkdir($kept);
        chmod($kept, permissions: 0o751);
        touch("{$kept}/file.txt");
        symlink($kept, $this->temporaryPath('linked/elsewhere'));

        $directory = $this->temporaryPath();
        $this->tearDownTemporaryDirectory();

        $survived = [file_exists("{$kept}/file.txt"), decoct(fileperms($kept) & 0o777)];
        unlink("{$kept}/file.txt");
        rmdir($kept);

        static::assertSame([false, [true, '751'], 0o077], [file_exists($directory), $survived, umask()]);
    }

    #[Test]
    public function theDirectoryIsAFreshOneUnderTheSystemsTemporaryDirectory(): void
    {
        static::assertSame(
            [true, true, 0],
            [
                is_dir($this->temporaryPath()),
                1 === preg_match(
                    '#^' . preg_quote(sys_get_temp_dir(), delimiter: '#') . '/contenir-test-[0-9a-f]{14}\.\d{8}$#',
                    $this->temporaryPath(),
                ),
                iterator_count(new FilesystemIterator($this->temporaryPath())),
            ],
        );
    }

    #[Test]
    public function writingAFileCreatesItsDirectoriesAndGivesItsPath(): void
    {
        $path = $this->writeTemporaryFile('a/b/c.txt', 'contents');

        static::assertSame(
            [$this->temporaryPath('a/b/c.txt'), 'contents'],
            [$path, file_get_contents($path)],
        );
    }

    #[Test]
    public function writingAFileIntoAnExistingDirectoryKeepsIt(): void
    {
        $this->writeTemporaryFile('a/first.txt', '1');
        $this->writeTemporaryFile('a/second.txt', '2');

        static::assertSame(['1', '2'], [
            file_get_contents($this->temporaryPath('a/first.txt')),
            file_get_contents($this->temporaryPath('a/second.txt')),
        ]);
    }

    protected function setUp(): void
    {
        $this->umask = umask(0o077);
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->temporaryPath())) {
            $this->tearDownTemporaryDirectory();
        }

        umask($this->umask);
    }
}
