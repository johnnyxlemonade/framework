<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

final class FileUploadProfileConfig
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
    ) {}
}
