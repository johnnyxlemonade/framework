<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\ValueObject;

use Lemonade\Framework\Upload\UploadVisibility;

/**
 * Describes a persisted upload while exposing a public-relative path only for public storage.
 */
class UploadedFile
{
    private readonly ?string $publicRelativePath;

    /**
     * Records the final storage identity, detected media type, byte count, and delivery visibility.
     */
    public function __construct(
        private readonly string $storedFilename,
        private readonly string $storedPath,
        private readonly string $storedRelativePath,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
        private readonly UploadVisibility $visibility = UploadVisibility::Public,
        ?string $publicRelativePath = null,
    ) {
        $this->publicRelativePath = $this->visibility === UploadVisibility::Public
            ? ($publicRelativePath ?? $this->storedRelativePath)
            : null;
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
     * Returns the storage-relative identifier below the public or private upload root.
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

    /**
     * Identifies whether the stored upload is publicly published or privately retained.
     */
    public function visibility(): UploadVisibility
    {
        return $this->visibility;
    }

    /**
     * Returns the web-root-relative path for public uploads and null for private uploads.
     */
    public function publicRelativePath(): ?string
    {
        return $this->publicRelativePath;
    }
}
