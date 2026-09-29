<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

/**
 * Carries image upload policy, including optional MIME narrowing beyond extension validation.
 */
final readonly class ImageUploadOptions
{
    /**
     * Creates an immutable image upload policy with optional MIME restrictions
     *
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private string $targetDirectory,
        private string $targetRelativeDirectory,
        private int $maxBytes = 5_242_880,
        private array $allowedMimeTypes = [],
        private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'],
        private bool $reencode = true,
        private ?int $minWidth = null,
        private ?int $maxWidth = null,
        private ?int $minHeight = null,
        private ?int $maxHeight = null,
    ) {
    }

    /**
     * Returns the absolute directory where the validated image will be published
     */
    public function targetDirectory(): string
    {
        return $this->targetDirectory;
    }

    /**
     * Returns the public-relative directory retained in the uploaded image reference
     */
    public function targetRelativeDirectory(): string
    {
        return $this->targetRelativeDirectory;
    }

    /**
     * Returns the largest accepted uploaded payload in bytes
     */
    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * Returns optional MIME values that further restrict catalog-compatible uploads
     *
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * Returns extensions explicitly permitted by this image upload policy
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * Reports whether validated source bytes are re-encoded before publication
     */
    public function reencode(): bool
    {
        return $this->reencode;
    }

    /**
     * Returns the optional lower bound for source image width
     */
    public function minWidth(): ?int
    {
        return $this->minWidth;
    }

    /**
     * Returns the optional upper bound for source image width
     */
    public function maxWidth(): ?int
    {
        return $this->maxWidth;
    }

    /**
     * Returns the optional lower bound for source image height
     */
    public function minHeight(): ?int
    {
        return $this->minHeight;
    }

    /**
     * Returns the optional upper bound for source image height
     */
    public function maxHeight(): ?int
    {
        return $this->maxHeight;
    }
}
