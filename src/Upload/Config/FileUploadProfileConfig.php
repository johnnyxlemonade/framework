<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

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
     */
    public function __construct(
        public string $targetDirectory,
        public int $maxBytes,
        public array $allowedExtensions,
    ) {
    }
}
