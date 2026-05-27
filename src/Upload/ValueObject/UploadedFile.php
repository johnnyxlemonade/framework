<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\ValueObject;

class UploadedFile
{
    public function __construct(
        private readonly string $storedFilename,
        private readonly string $storedPath,
        private readonly string $storedRelativePath,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
    ) {}

    public function storedFilename(): string
    {
        return $this->storedFilename;
    }

    public function storedPath(): string
    {
        return $this->storedPath;
    }

    public function storedRelativePath(): string
    {
        return $this->storedRelativePath;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }
}
