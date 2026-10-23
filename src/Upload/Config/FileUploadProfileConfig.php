<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

use Lemonade\Framework\Mime\MimeRisk;
use Lemonade\Framework\Upload\UploadVisibility;

/**
 * Carries one resolved generic-upload policy after extension validation and byte-size parsing.
 *
 * Allowed extensions are the canonical allowlist; their server-detected MIME compatibility remains enforced by
 * MimeTypeCatalog at upload time.
 */
final readonly class FileUploadProfileConfig
{
    /**
     * Records the normalized profile values consumed by UploadFactory.
     *
     * @param list<string> $allowedExtensions Explicit normalized filename suffixes permitted by this profile
     * @param list<MimeRisk> $allowedRisks Non-passive categories explicitly authorized for private storage
     */
    public function __construct(
        private string $targetDirectory,
        private int $maxBytes,
        private array $allowedExtensions,
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

    /** Returns whether accepted files are published to public or private storage. */
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
