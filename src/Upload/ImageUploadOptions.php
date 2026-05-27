<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

final class ImageUploadOptions
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private readonly string $targetDirectory,
        private readonly string $targetRelativeDirectory,
        private readonly int $maxBytes = 5_242_880,
        private readonly array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        private readonly array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        private readonly bool $reencode = true,
        private readonly ?int $minWidth = null,
        private readonly ?int $maxWidth = null,
        private readonly ?int $minHeight = null,
        private readonly ?int $maxHeight = null,
    ) {}

    public function targetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function targetRelativeDirectory(): string
    {
        return $this->targetRelativeDirectory;
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    public function reencode(): bool
    {
        return $this->reencode;
    }

    public function minWidth(): ?int
    {
        return $this->minWidth;
    }

    public function maxWidth(): ?int
    {
        return $this->maxWidth;
    }

    public function minHeight(): ?int
    {
        return $this->minHeight;
    }

    public function maxHeight(): ?int
    {
        return $this->maxHeight;
    }
}
