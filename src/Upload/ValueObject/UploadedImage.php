<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\ValueObject;

final class UploadedImage extends UploadedFile
{
    public function __construct(
        string $storedFilename,
        string $storedPath,
        string $storedRelativePath,
        string $mimeType,
        int $sizeBytes,
        private readonly int $width,
        private readonly int $height,
    ) {
        parent::__construct(
            storedFilename: $storedFilename,
            storedPath: $storedPath,
            storedRelativePath: $storedRelativePath,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
        );
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }
}
