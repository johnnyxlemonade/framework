<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Filesystem;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use PHPUnit\Framework\TestCase;

final class LockManagerTest extends TestCase
{
    private string $root = '';
    /**
     * @var LockManager
     */
    private $manager;

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
        $caught = null;

        try {
            $this->manager->lock($lockFile, static function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (FilesystemException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(FilesystemException::class, $caught);
        self::assertInstanceOf(\RuntimeException::class, $caught->getPrevious());
        self::assertSame('boom', $caught->getPrevious()->getMessage());
    }

    public function testUnlockFailureIsReportedWhenCallbackSucceeds(): void
    {
        $scheme = 'lemonade-unlock-failure';
        self::assertTrue(stream_wrapper_register($scheme, UnlockFailureStreamWrapper::class));

        try {
            $this->manager->lock($scheme . '://locks/test.lock', static fn(): string => 'done');
            self::fail('An unlock failure must be reported.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_LOCK_FAILED, $exception->getCode());
            self::assertStringContainsString('release', $exception->getMessage());
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    public function testCallbackFailureRemainsPrimaryWhenUnlockAlsoFails(): void
    {
        $scheme = 'lemonade-callback-unlock-failure';
        self::assertTrue(stream_wrapper_register($scheme, UnlockFailureStreamWrapper::class));
        $caught = null;

        try {
            $this->manager->lock($scheme . '://locks/test.lock', static function (): void {
                throw new \RuntimeException('callback-primary');
            });
        } catch (FilesystemException $exception) {
            $caught = $exception;
        } finally {
            stream_wrapper_unregister($scheme);
        }

        self::assertInstanceOf(FilesystemException::class, $caught);
        self::assertSame(FilesystemException::CODE_LOCK_FAILED, $caught->getCode());
        self::assertInstanceOf(\RuntimeException::class, $caught->getPrevious());
        self::assertSame('callback-primary', $caught->getPrevious()->getMessage());
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

final class UnlockFailureStreamWrapper
{
    public mixed $context = null;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_lock(int $operation): bool
    {
        return $operation !== LOCK_UN;
    }

    public function stream_close(): void
    {
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return [2 => 0100666, 'mode' => 0100666];
    }

    /**
     * @return array<int|string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        return [2 => 0040777, 'mode' => 0040777];
    }
}
