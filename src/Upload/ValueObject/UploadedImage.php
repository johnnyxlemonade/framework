<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\ValueObject;

/**
 * Extends a persisted upload reference with dimensions verified from the final bitmap bytes.
 */
final class UploadedImage extends UploadedFile
{
    /**
     * Records the final storage metadata and dimensions of a successfully processed bitmap.
     */
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

    /**
     * Returns the verified final bitmap width in pixels.
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Returns the verified final bitmap height in pixels.
     */
    public function height(): int
    {
        return $this->height;
    }
}
