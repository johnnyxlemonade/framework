<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

use Lemonade\Framework\Mime\MimeRisk;

/**
 * Defines the resolved generic-upload policy passed to validation and storage services.
 *
 * Extensions are an explicit allowlist and are paired with server-detected MIME values through MimeTypeCatalog.
 */
final readonly class FileUploadOptions
{
    /**
     * Creates a policy with an absolute constrained target and its visibility-specific storage-relative counterpart.
     *
     * @param list<string> $allowedExtensions Explicit normalized filename suffixes permitted by this upload operation
     * @param list<MimeRisk> $allowedRisks Non-passive categories explicitly authorized for private storage
     */
    public function __construct(
        private string $targetDirectory,
        private string $targetRelativeDirectory,
        private int $maxBytes = 10_485_760,
        private array $allowedExtensions = [],
        private UploadVisibility $visibility = UploadVisibility::Public,
        private array $allowedRisks = [],
    ) {
    }

    /**
     * Provides the absolute directory selected for final file publication.
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
     * Provides the positive server-enforced payload limit in bytes.
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
     * Identifies whether the validated file is published publicly or retained in private writable storage.
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
