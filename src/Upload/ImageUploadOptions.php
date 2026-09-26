<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

final readonly class ImageUploadOptions
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private string $targetDirectory,
        private string $targetRelativeDirectory,
        private int $maxBytes = 5_242_880,
        private array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        private bool $reencode = true,
        private ?int $minWidth = null,
        private ?int $maxWidth = null,
        private ?int $minHeight = null,
        private ?int $maxHeight = null,
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
