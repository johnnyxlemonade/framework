<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

final class ImageUploadProfileConfig
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        public readonly string $targetDirectory,
        public readonly int $maxBytes,
        public readonly array $allowedMimeTypes,
        public readonly array $allowedExtensions,
        public readonly bool $reencode,
        public readonly ?int $minWidth,
        public readonly ?int $maxWidth,
        public readonly ?int $minHeight,
        public readonly ?int $maxHeight,
    ) {}
}
