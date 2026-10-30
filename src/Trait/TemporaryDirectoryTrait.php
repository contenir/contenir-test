<?php

declare(strict_types=1);

namespace Contenir\Test\Trait;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_reverse;
use function chmod;
use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function umask;
use function uniqid;
use function unlink;

/**
 * A private scratch directory for each test, removed afterwards even when the
 * test left read-only directories or files, or symlinks out of it, behind.
 * Symlinks are removed, never followed.
 *
 * The umask is 022 while the test runs, so the permissions it creates files
 * with are the same on every machine, and is restored afterwards.
 *
 * Call setUpTemporaryDirectory() from setUp() and tearDownTemporaryDirectory()
 * from tearDown().
 *
 * @api
 */
trait TemporaryDirectoryTrait
{
    private string $temporaryDirectory;

    private int $temporaryDirectoryUmask;

    protected function setUpTemporaryDirectory(): void
    {
        $this->temporaryDirectoryUmask = umask(0o022);
        $this->temporaryDirectory      = sys_get_temp_dir() . '/contenir-test-' . uniqid(more_entropy: true);
        mkdir($this->temporaryDirectory, permissions: 0o777, recursive: true);
    }

    protected function tearDownTemporaryDirectory(): void
    {
        umask($this->temporaryDirectoryUmask);

        if (! is_dir($this->temporaryDirectory)) {
            return;
        }

        chmod($this->temporaryDirectory, permissions: 0o700);
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->temporaryDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var list<SplFileInfo> $found */
        $found = [];
        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            if ($item->isDir() && ! $item->isLink()) {
                chmod($item->getPathname(), permissions: 0o700);
            }

            $found[] = $item;
        }

        foreach (array_reverse($found) as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->temporaryDirectory);
    }

    /**
     * The absolute path of $relative in the scratch directory, or of the directory itself.
     */
    protected function temporaryPath(string $relative = ''): string
    {
        return '' === $relative ? $this->temporaryDirectory : "{$this->temporaryDirectory}/{$relative}";
    }

    /**
     * Writes $contents to $relative in the scratch directory, creating its parent
     * directories, and returns its absolute path.
     */
    protected function writeTemporaryFile(string $relative, string $contents): string
    {
        $path = $this->temporaryPath($relative);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), permissions: 0o777, recursive: true);
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
