<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use PHPUnit\Framework\TestCase;

final class UploadedFileTest extends TestCase
{
    public function testResultPreservesPrivateStoragePaths(): void
    {
        $uploaded = new UploadedFile(
            storedFilename: 'stored.txt',
            storedPath: '/app/storage/uploads/documents/stored.txt',
            storedRelativePath: 'uploads/documents/stored.txt',
            mimeType: 'text/plain',
            sizeBytes: 4,
        );

        self::assertSame('/app/storage/uploads/documents/stored.txt', $uploaded->storedPath());
        self::assertSame('uploads/documents/stored.txt', $uploaded->storedRelativePath());
    }

}
