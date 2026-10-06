<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload;

/**
 * Defines the resolved generic-upload policy passed to validation and storage services.
 *
 * Extensions are an explicit allowlist and are paired with server-detected MIME values through MimeTypeCatalog.
 */
final readonly class FileUploadOptions
{
    /**
     * Creates a policy with an absolute storage target and its public-relative counterpart.
     *
     * @param list<string> $allowedExtensions Explicit normalized filename suffixes permitted by this upload operation
     */
    public function __construct(
        private string $targetDirectory,
        private string $targetRelativeDirectory,
        private int $maxBytes = 10_485_760,
        private array $allowedExtensions = [],
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
     * Provides the public-relative directory retained in the returned upload reference.
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
}
