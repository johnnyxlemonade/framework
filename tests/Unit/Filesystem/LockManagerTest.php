<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Filesystem;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use PHPUnit\Framework\TestCase;

final class LockManagerTest extends TestCase
{
    private string $root;
    private LockManager $manager;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-lock-' . uniqid('', true);
        $this->manager = new LockManager(new DirectoryManager());
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testLockCreatesParentDirectoryAndLockFileAndReturnsCallbackValue(): void
    {
        $lockFile = $this->path('locks/my.lock');

        $result = $this->manager->lock($lockFile, static fn(): string => 'ok');

        self::assertSame('ok', $result);
        self::assertDirectoryExists(dirname($lockFile));
        self::assertFileExists($lockFile);
    }

    public function testLockWrapsCallbackExceptionIntoFilesystemExceptionWithPrevious(): void
    {
        $lockFile = $this->path('locks/fail.lock');

        try {
            $this->manager->lock($lockFile, static function (): never {
                throw new \RuntimeException('boom');
            });
            self::fail('Expected FilesystemException was not thrown.');
        } catch (FilesystemException $exception) {
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
            self::assertSame('boom', $exception->getPrevious()->getMessage());
        }
    }

    private function path(string $relative): string
    {
        return $this->root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
