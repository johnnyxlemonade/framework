<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Filesystem;

use Lemonade\Framework\Filesystem\Exception\FilesystemException;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use PHPUnit\Framework\TestCase;

final class FileManagerTest extends TestCase
{
    private string $root;
    private FileManager $manager;
    private DirectoryManager $dir;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-file-' . uniqid('', true);
        $this->manager = new FileManager();
        $this->dir = new DirectoryManager();
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testReadReturnsFileContent(): void
    {
        $file = $this->path('read.txt');
        $this->dir->write($file, 'hello');

        self::assertSame('hello', $this->manager->read($file));
    }

    public function testReadThrowsForMissingFile(): void
    {
        $this->expectException(FilesystemException::class);
        $this->manager->read($this->path('missing.txt'));
    }

    public function testSizeReturnsFileSize(): void
    {
        $file = $this->path('size.txt');
        $this->dir->write($file, '12345');

        self::assertSame(5, $this->manager->size($file));
    }

    public function testModifiedReturnsUnixTimestamp(): void
    {
        $file = $this->path('mtime.txt');
        $this->dir->write($file, 'x');

        $modified = $this->manager->modified($file);
        self::assertGreaterThan(0, $modified);
    }

    public function testHashReturnsExpectedSha256(): void
    {
        $file = $this->path('hash.txt');
        $this->dir->write($file, 'hello');

        self::assertSame(hash('sha256', 'hello'), $this->manager->hash($file, 'sha256'));
    }

    public function testMimeReturnsNonEmptyStringForTextFile(): void
    {
        $file = $this->path('mime.txt');
        $this->dir->write($file, 'plain text');

        $mime = $this->manager->mime($file);
        self::assertNotSame('', $mime);
    }

    public function testPermissionsReturnsIntInRange0To0777(): void
    {
        $file = $this->path('perm.txt');
        $this->dir->write($file, 'x');

        $permissions = $this->manager->permissions($file);
        self::assertGreaterThanOrEqual(0, $permissions);
        self::assertLessThanOrEqual(0777, $permissions);
    }

    public function testSizeThrowsForMissingFile(): void
    {
        $this->expectException(FilesystemException::class);
        $this->manager->size($this->path('missing.txt'));
    }

    public function testHashThrowsForMissingFile(): void
    {
        $this->expectException(FilesystemException::class);
        $this->manager->hash($this->path('missing.txt'));
    }

    public function testMimeThrowsForMissingFile(): void
    {
        $this->expectException(FilesystemException::class);
        $this->manager->mime($this->path('missing.txt'));
    }

    public function testPermissionsThrowsForMissingFile(): void
    {
        $this->expectException(FilesystemException::class);
        $this->manager->permissions($this->path('missing.txt'));
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
