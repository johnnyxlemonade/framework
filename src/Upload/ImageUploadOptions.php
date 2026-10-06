<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

/**
 * Carries image upload policy with catalog-backed extension validation.
 */
final readonly class ImageUploadOptions
{
    /**
     * Creates an immutable image policy that applies catalog MIME matching before image decoding.
     *
     * @param list<string> $allowedExtensions Explicit normalized filename suffixes permitted by this upload operation
     */
    public function __construct(
        private string $targetDirectory,
        private string $targetRelativeDirectory,
        private int $maxBytes = 5_242_880,
        private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'],
        private bool $reencode = true,
        private ?int $minWidth = null,
        private ?int $maxWidth = null,
        private ?int $minHeight = null,
        private ?int $maxHeight = null,
    ) {
    }

    /**
     * Provides the absolute directory where a validated image is published.
     */
    public function targetDirectory(): string
    {
        return $this->targetDirectory;
    }

    /**
     * Provides the public-relative directory retained in the uploaded image reference.
     */
    public function targetRelativeDirectory(): string
    {
        return $this->targetRelativeDirectory;
    }

    /**
     * Provides the positive server-enforced source-payload limit in bytes.
     */
    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * Provides normalized filename suffixes that may proceed to catalog MIME matching.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * Indicates whether validated source bytes are re-encoded before publication.
     */
    public function reencode(): bool
    {
        return $this->reencode;
    }

    /**
     * Provides the optional inclusive lower bound for decoded source-image width.
     */
    public function minWidth(): ?int
    {
        return $this->minWidth;
    }

    /**
     * Provides the optional inclusive upper bound for decoded source-image width.
     */
    public function maxWidth(): ?int
    {
        return $this->maxWidth;
    }

    /**
     * Provides the optional inclusive lower bound for decoded source-image height.
     */
    public function minHeight(): ?int
    {
        return $this->minHeight;
    }

    /**
     * Provides the optional inclusive upper bound for decoded source-image height.
     */
    public function maxHeight(): ?int
    {
        return $this->maxHeight;
    }
}
