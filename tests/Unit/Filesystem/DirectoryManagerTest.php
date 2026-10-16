<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Filesystem;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Throwable;

final class DirectoryManagerTest extends TestCase
{
    private string $root = '';
    /**
     * @var DirectoryManager
     */
    private $manager;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-dir-' . uniqid('', true);
        $this->manager = new DirectoryManager();
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testCreateCreatesNestedDirectory(): void
    {
        $path = $this->path('a/b/c');
        $this->manager->create($path);

        self::assertDirectoryExists($path);
    }

    public function testCreateIsIdempotentWhenDirectoryExists(): void
    {
        $path = $this->path('exists');
        $this->manager->create($path);
        $this->manager->create($path);

        self::assertDirectoryExists($path);
    }

    public function testCreateToleratesConcurrentCreationWhenWarningsBecomeExceptions(): void
    {
        $this->manager->create($this->root);

        $directory = $this->path('concurrent');
        $barrier = $this->path('barrier');
        $worker = $this->path('create-directory-worker.php');
        $autoload = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

        file_put_contents($worker, sprintf(<<<'PHP'
<?php

declare(strict_types=1);

require %s;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$ready = $argv[2] . '.' . getmypid();
touch($ready);

while (!is_file($argv[3])) {
    usleep(1_000);
}

(new \Lemonade\Framework\Filesystem\Manager\DirectoryManager())->create($argv[1]);
PHP, var_export($autoload, true)));

        $processes = [];
        $workerCount = 8;

        for ($index = 0; $index < $workerCount; ++$index) {
            $process = proc_open(
                [PHP_BINARY, $worker, $directory, $barrier . '.ready', $barrier . '.go'],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
            );

            self::assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }

        $readyPaths = glob($barrier . '.ready.*');
        $readyCount = $readyPaths === false ? 0 : count($readyPaths);

        for ($attempt = 0; $attempt < 200 && $readyCount < $workerCount; ++$attempt) {
            usleep(10_000);

            $readyPaths = glob($barrier . '.ready.*');
            $readyCount = $readyPaths === false ? 0 : count($readyPaths);
        }

        touch($barrier . '.go');

        self::assertSame($workerCount, $readyCount);

        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), is_string($stdout) ? $stdout . $stderr : $stderr);
        }

        self::assertDirectoryExists($directory);
    }

    public function testWriteCreatesParentAndWritesContent(): void
    {
        $file = $this->path('nested/file.txt');
        $this->manager->write($file, 'hello');

        self::assertFileExists($file);
        self::assertSame('hello', file_get_contents($file));
    }

    public function testCopyCopiesFileToNewPath(): void
    {
        $src = $this->path('src.txt');
        $dst = $this->path('out/dst.txt');
        $this->manager->write($src, 'copy-me');

        $this->manager->copy($src, $dst);

        self::assertSame('copy-me', file_get_contents($dst));
    }

    public function testCopyThrowsWhenSourceDoesNotExist(): void
    {
        try {
            $this->manager->copy($this->path('missing.txt'), $this->path('dst.txt'));
            self::fail('A missing copy source must fail.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_PATH_NOT_FOUND, $exception->getCode());
        }
    }

    public function testCopyThrowsWhenDestinationExistsAndOverwriteFalse(): void
    {
        $src = $this->path('src.txt');
        $dst = $this->path('dst.txt');
        $this->manager->write($src, 'src');
        $this->manager->write($dst, 'dst');

        $this->expectException(FilesystemException::class);
        $this->manager->copy($src, $dst, false);
    }

    public function testCopyOverwritesExistingFileWhenOverwriteTrue(): void
    {
        $src = $this->path('src.txt');
        $dst = $this->path('dst.txt');
        $this->manager->write($src, 'new');
        $this->manager->write($dst, 'old');

        $this->manager->copy($src, $dst, true);

        self::assertSame('new', file_get_contents($dst));
    }

    public function testCopyRejectsSameFileBeforeOpeningDestination(): void
    {
        $file = $this->path('same-file.txt');
        $this->manager->write($file, 'preserve-me');

        try {
            $this->manager->copy($file, $file, true);
            self::fail('Copying a file onto itself must fail.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_FILE_CONFLICT, $exception->getCode());
        }

        self::assertSame('preserve-me', file_get_contents($file));
    }

    public function testCopyRejectsNormalizedEquivalentPaths(): void
    {
        $directory = $this->path('normalized');
        $this->manager->create($directory . DIRECTORY_SEPARATOR . 'nested');
        $file = $directory . DIRECTORY_SEPARATOR . 'source.txt';
        $this->manager->write($file, 'preserve-me');
        $equivalent = $directory
            . DIRECTORY_SEPARATOR . 'nested'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '.'
            . DIRECTORY_SEPARATOR . 'source.txt';

        try {
            $this->manager->copy($file, $equivalent, true);
            self::fail('Equivalent normalized paths must be rejected.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_FILE_CONFLICT, $exception->getCode());
        }

        self::assertSame('preserve-me', file_get_contents($file));
    }

    public function testCopyRejectsDirectoryCopiedIntoOwnDescendant(): void
    {
        $source = $this->path('source-tree');
        $this->manager->write($source . DIRECTORY_SEPARATOR . 'file.txt', 'content');
        $this->manager->create($source . DIRECTORY_SEPARATOR . 'existing');
        $descendant = $source
            . DIRECTORY_SEPARATOR . 'existing'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'copy';

        try {
            $this->manager->copy($source, $descendant, true);
            self::fail('A directory must not be copied into its own descendant.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_FILE_CONFLICT, $exception->getCode());
        }

        self::assertFileDoesNotExist($source . DIRECTORY_SEPARATOR . 'copy' . DIRECTORY_SEPARATOR . 'file.txt');
    }

    public function testCopyCopiesDirectoryRecursivelyIncludingNestedFiles(): void
    {
        $srcDir = $this->path('src-dir');
        $this->manager->write($srcDir . DIRECTORY_SEPARATOR . 'a.txt', 'a');
        $this->manager->write($srcDir . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'b.txt', 'b');

        $dstDir = $this->path('dst-dir');
        $this->manager->copy($srcDir, $dstDir);

        self::assertSame('a', file_get_contents($dstDir . DIRECTORY_SEPARATOR . 'a.txt'));
        self::assertSame('b', file_get_contents($dstDir . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'b.txt'));
    }

    public function testDeleteRemovesFile(): void
    {
        $file = $this->path('delete.txt');
        $this->manager->write($file, 'x');

        $this->manager->delete($file);

        self::assertFileDoesNotExist($file);
    }

    public function testFileDeleteFailureUsesFileDeleteCode(): void
    {
        $directory = $this->path('delete-failure');
        $file = $directory . DIRECTORY_SEPARATOR . 'file.txt';
        $this->manager->write($file, 'content');
        chmod($directory, 0555);
        clearstatcache(true, $directory);

        if (is_writable($directory)) {
            chmod($directory, 0775);
            self::markTestSkipped('The test process can write to read-only directories.');
        }

        try {
            try {
                $this->manager->delete($file);
                self::fail('Deleting a file without directory permission must fail.');
            } catch (FilesystemException $exception) {
                self::assertSame(FilesystemException::CODE_FILE_DELETE_FAILED, $exception->getCode());
            }
        } finally {
            chmod($directory, 0775);
        }

        self::assertFileExists($file);
    }

    public function testDeleteRemovesDirectoryRecursively(): void
    {
        $dir = $this->path('dir');
        $this->manager->write($dir . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'file.txt', 'x');

        $this->manager->delete($dir);

        self::assertDirectoryDoesNotExist($dir);
    }

    public function testStreamReturnsFilenameToRealPathEntries(): void
    {
        $dir = $this->path('stream');
        $this->manager->write($dir . DIRECTORY_SEPARATOR . 'one.txt', '1');
        $this->manager->write($dir . DIRECTORY_SEPARATOR . 'two.txt', '2');

        $items = iterator_to_array($this->manager->stream($dir, false));

        self::assertArrayHasKey('one.txt', $items);
        self::assertArrayHasKey('two.txt', $items);
        self::assertSame(realpath($dir . DIRECTORY_SEPARATOR . 'one.txt'), $items['one.txt']);
    }

    public function testStreamIsRecursiveByDefault(): void
    {
        $dir = $this->path('stream-default');
        $deepFile = $dir . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'file.txt';
        $this->manager->write($deepFile, 'content');

        $items = iterator_to_array($this->manager->stream($dir));

        self::assertSame(realpath($deepFile), $items['file.txt']);
    }

    public function testStreamThrowsForMissingDirectory(): void
    {
        $this->expectException(FilesystemException::class);
        iterator_to_array($this->manager->stream($this->path('missing')));
    }

    public function testTreeReturnsSplFileInfoItems(): void
    {
        $dir = $this->path('tree');
        $this->manager->write($dir . DIRECTORY_SEPARATOR . 'deep' . DIRECTORY_SEPARATOR . 'file.txt', 'x');

        $items = iterator_to_array($this->manager->tree($dir, true));

        self::assertNotEmpty($items);
        foreach ($items as $item) {
            self::assertInstanceOf(SplFileInfo::class, $item);
        }
    }

    public function testFindFindsFilesByGlobPattern(): void
    {
        $dir = $this->path('find');
        $this->manager->write($dir . DIRECTORY_SEPARATOR . 'one.log', '1');
        $this->manager->write($dir . DIRECTORY_SEPARATOR . 'two.txt', '2');
        $this->manager->write($dir . DIRECTORY_SEPARATOR . 'three.log', '3');

        $items = iterator_to_array($this->manager->find('*.log', $dir));
        sort($items);

        self::assertCount(2, $items);
        self::assertStringEndsWith('one.log', $items[0]);
        self::assertStringEndsWith('three.log', $items[1]);
    }

    public function testFindThrowsForMissingDirectory(): void
    {
        $this->expectException(FilesystemException::class);
        iterator_to_array($this->manager->find('*.txt', $this->path('missing')));
    }

    public function testFindRejectsTraversalPatternsWithStableCode(): void
    {
        $directory = $this->path('find-contained');
        $this->manager->create($directory);

        foreach (['../*.php', '../../*'] as $pattern) {
            try {
                iterator_to_array($this->manager->find($pattern, $directory));
                self::fail("Traversal pattern '{$pattern}' must be rejected.");
            } catch (FilesystemException $exception) {
                self::assertSame(FilesystemException::CODE_FIND_PATTERN_INVALID, $exception->getCode());
            }
        }
    }

    public function testRecursiveOperationsDoNotFollowSymlinkOutsideSourceTree(): void
    {
        $source = $this->path('symlink-source');
        $outside = $this->path('outside');
        $link = $source . DIRECTORY_SEPARATOR . 'escape';
        $this->manager->write($outside . DIRECTORY_SEPARATOR . 'secret.txt', 'secret');
        $this->manager->create($source);

        if (!@symlink($outside, $link)) {
            self::markTestSkipped('Symbolic links are unavailable on this platform.');
        }

        $streamed = iterator_to_array($this->manager->stream($source));
        self::assertSame($link, $streamed['escape']);
        self::assertArrayNotHasKey('secret.txt', $streamed);

        $found = iterator_to_array($this->manager->find('*', $source));
        self::assertSame([$link], $found);

        $destination = $this->path('symlink-copy');
        try {
            $this->manager->copy($source, $destination);
            self::fail('Directory copy must reject symbolic-link entries.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_COPY_FAILED, $exception->getCode());
        }

        self::assertDirectoryDoesNotExist($destination);

        $this->manager->delete($link);
        self::assertFileDoesNotExist($link);
        self::assertFileExists($outside . DIRECTORY_SEPARATOR . 'secret.txt');
    }

    public function testFailedCopyPreservesExistingDestinationAndWrapsNativeFailure(): void
    {
        $destination = $this->path('copy-destination.txt');
        $this->manager->write($destination, 'original');
        $scheme = 'lemonade-failing-read';
        self::assertTrue(stream_wrapper_register($scheme, FailingReadStreamWrapper::class));

        try {
            $this->manager->copy($scheme . '://source', $destination, true);
            self::fail('The failing source stream must produce a filesystem exception.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_COPY_FAILED, $exception->getCode());
            self::assertInstanceOf(Throwable::class, $exception->getPrevious());
        } finally {
            stream_wrapper_unregister($scheme);
        }

        self::assertSame('original', file_get_contents($destination));
    }

    public function testFailedWritePreservesExistingDestination(): void
    {
        $directory = $this->path('write-failure');
        $destination = $directory . DIRECTORY_SEPARATOR . 'destination.txt';
        $this->manager->write($destination, 'original');
        chmod($directory, 0555);
        clearstatcache(true, $directory);

        if (is_writable($directory)) {
            chmod($directory, 0775);
            self::markTestSkipped('The test process can write to read-only directories.');
        }

        try {
            try {
                $this->manager->write($destination, 'replacement');
                self::fail('Writing without permission must fail.');
            } catch (FilesystemException $exception) {
                self::assertSame(FilesystemException::CODE_FILE_WRITE_FAILED, $exception->getCode());
            }
        } finally {
            chmod($directory, 0775);
        }

        self::assertSame('original', file_get_contents($destination));
    }

    public function testNativeWarningIsTranslatedToFilesystemException(): void
    {
        $directory = $this->path('target-directory');
        $this->manager->create($directory);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $this->manager->write($directory, 'content');
            self::fail('Publishing a file over a directory must fail.');
        } catch (FilesystemException $exception) {
            self::assertSame(FilesystemException::CODE_FILE_WRITE_FAILED, $exception->getCode());
            self::assertInstanceOf(\ErrorException::class, $exception->getPrevious());
        } finally {
            restore_error_handler();
        }

        self::assertDirectoryExists($directory);
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

final class FailingReadStreamWrapper
{
    public mixed $context = null;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        throw new \RuntimeException('Simulated source read failure.');
    }

    public function stream_eof(): bool
    {
        return false;
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }
}
