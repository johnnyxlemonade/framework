<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Image;

use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Image\FilesystemImageFileWriter;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use PHPUnit\Framework\TestCase;

final class FilesystemImageFileWriterTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-image-writer-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        (new DirectoryManager())->delete($this->root);
    }

    public function testWriteUsesExplicitPublicFileAndDirectoryPermissions(): void
    {
        $directory = new DirectoryManager();
        $writer = new FilesystemImageFileWriter(new Filesystem(
            $directory,
            new FileManager(),
            new LockManager($directory),
        ));
        $target = $this->root . '/images/original.png';
        $previousUmask = umask(0000);

        try {
            $writer->write(
                new EncodedImage('png-bytes', ImageFormat::Png, new ImageDimensions(1, 1)),
                $target,
            );
        } finally {
            umask($previousUmask);
        }

        self::assertSame('png-bytes', file_get_contents($target));
        self::assertSame(0644, fileperms($target) & 0777);
        self::assertSame(0755, fileperms(dirname($target)) & 0777);
    }
}
