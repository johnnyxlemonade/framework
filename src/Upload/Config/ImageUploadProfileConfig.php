<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

final readonly class ImageUploadProfileConfig
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        public string $targetDirectory,
        public int $maxBytes,
        public array $allowedMimeTypes,
        public array $allowedExtensions,
        public bool $reencode,
        public ?int $minWidth,
        public ?int $maxWidth,
        public ?int $minHeight,
        public ?int $maxHeight,
    ) {
    }
}
