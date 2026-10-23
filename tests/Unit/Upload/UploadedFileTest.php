<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Upload;

use Lemonade\Framework\Upload\UploadVisibility;
use Lemonade\Framework\Upload\ValueObject\UploadedFile;
use PHPUnit\Framework\TestCase;

final class UploadedFileTest extends TestCase
{
    public function testPrivateResultNeverExposesPublicRelativePath(): void
    {
        $uploaded = new UploadedFile(
            storedFilename: 'stored.txt',
            storedPath: '/app/storage/writable/uploads/files/documents/stored.txt',
            storedRelativePath: 'uploads/files/documents/stored.txt',
            mimeType: 'text/plain',
            sizeBytes: 4,
            visibility: UploadVisibility::Private,
            publicRelativePath: 'uploads/unsafe/stored.txt',
        );

        self::assertNull($uploaded->publicRelativePath());
    }

    public function testPublicResultPreservesBackwardCompatibleRelativePath(): void
    {
        $uploaded = new UploadedFile(
            storedFilename: 'stored.txt',
            storedPath: '/app/public/uploads/documents/stored.txt',
            storedRelativePath: 'uploads/documents/stored.txt',
            mimeType: 'text/plain',
            sizeBytes: 4,
        );

        self::assertSame('uploads/documents/stored.txt', $uploaded->publicRelativePath());
    }
}
