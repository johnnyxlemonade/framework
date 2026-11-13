<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\ValueObject;

/**
 * Describes a persisted private upload without exposing a public URL.
 */
class UploadedFile
{
    /**
     * Records the final storage identity, detected media type, and byte count.
     */
    public function __construct(
        private readonly string $storedFilename,
        private readonly string $storedPath,
        private readonly string $storedRelativePath,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
    ) {
    }

    /**
     * Returns the generated basename used in the selected storage directory.
     */
    public function storedFilename(): string
    {
        return $this->storedFilename;
    }

    /**
     * Returns the internal absolute filesystem path intended for trusted application-side operations.
     */
    public function storedPath(): string
    {
        return $this->storedPath;
    }

    /**
     * Returns the identifier relative to application storage.
     */
    public function storedRelativePath(): string
    {
        return $this->storedRelativePath;
    }

    /**
     * Returns the server-detected media type retained after successful validation.
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Returns the final persisted byte count.
     */
    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }
}
