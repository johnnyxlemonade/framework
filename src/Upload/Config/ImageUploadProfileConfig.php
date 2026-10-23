<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Upload\UploadVisibility;

/**
 * Carries one resolved image-upload policy, including re-encoding and dimension boundaries.
 *
 * Allowed extensions are the canonical allowlist; their server-detected MIME compatibility remains enforced by
 * MimeTypeCatalog before image-specific validation runs.
 */
final readonly class ImageUploadProfileConfig
{
    /**
     * Records normalized image policy values consumed by UploadFactory.
     *
     * @param list<string> $allowedExtensions Explicit normalized filename suffixes permitted by this profile
     * @param list<MimeRisk> $allowedRisks Non-passive categories explicitly authorized for private storage
     */
    public function __construct(
        private string $targetDirectory,
        private int $maxBytes,
        private array $allowedExtensions,
        private bool $reencode,
        private ?int $minWidth,
        private ?int $maxWidth,
        private ?int $minHeight,
        private ?int $maxHeight,
        private UploadVisibility $visibility = UploadVisibility::Public,
        private array $allowedRisks = [],
    ) {
    }

    /** Returns the validated relative directory configured for this profile. */
    public function targetDirectory(): string
    {
        return $this->targetDirectory;
    }

    /** Returns the maximum number of bytes accepted while staging an upload. */
    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /**
     * Returns the normalized filename extensions accepted by this profile.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /** Returns whether validated image bytes are regenerated before publication. */
    public function reencode(): bool
    {
        return $this->reencode;
    }

    /** Returns the optional minimum accepted image width in pixels. */
    public function minWidth(): ?int
    {
        return $this->minWidth;
    }

    /** Returns the optional maximum accepted image width in pixels. */
    public function maxWidth(): ?int
    {
        return $this->maxWidth;
    }

    /** Returns the optional minimum accepted image height in pixels. */
    public function minHeight(): ?int
    {
        return $this->minHeight;
    }

    /** Returns the optional maximum accepted image height in pixels. */
    public function maxHeight(): ?int
    {
        return $this->maxHeight;
    }

    /** Returns whether accepted images are published to public or private storage. */
    public function visibility(): UploadVisibility
    {
        return $this->visibility;
    }

    /**
     * Returns the non-passive MIME risk categories explicitly allowed for private storage.
     *
     * @return list<MimeRisk>
     */
    public function allowedRisks(): array
    {
        return $this->allowedRisks;
    }
}
