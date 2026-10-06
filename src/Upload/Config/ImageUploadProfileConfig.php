<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

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
     */
    public function __construct(
        public string $targetDirectory,
        public int $maxBytes,
        public array $allowedExtensions,
        public bool $reencode,
        public ?int $minWidth,
        public ?int $maxWidth,
        public ?int $minHeight,
        public ?int $maxHeight,
    ) {
    }
}
