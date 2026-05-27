<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Filesystem;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

final class DirectoryManagerTest extends TestCase
{
    private string $root;
    private DirectoryManager $manager;

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
        $this->expectException(FilesystemException::class);
        $this->manager->copy($this->path('missing.txt'), $this->path('dst.txt'));
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
