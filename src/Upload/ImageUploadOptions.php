<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Mime\MimeRisk;

/**
 * Carries image upload policy with catalog-backed extension validation.
 */
final readonly class ImageUploadOptions
{
    /**
     * Creates an immutable image policy that applies catalog MIME matching before image decoding.
     *
     * @param list<string> $allowedExtensions Explicit normalized filename suffixes permitted by this upload operation
     * @param list<MimeRisk> $allowedRisks Non-passive categories explicitly authorized for private storage
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
        private UploadVisibility $visibility = UploadVisibility::Public,
        private array $allowedRisks = [],
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
     * Provides the directory retained relative to either the public root or private writable root.
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

    /**
     * Identifies whether the validated image is published publicly or retained in private writable storage.
     */
    public function visibility(): UploadVisibility
    {
        return $this->visibility;
    }

    /**
     * Provides the non-passive risk categories explicitly authorized for private storage.
     *
     * @return list<MimeRisk>
     */
    public function allowedRisks(): array
    {
        return $this->allowedRisks;
    }
}
