<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

final class UploadConfig
{
    /**
     * @param array<string, FileUploadProfileConfig> $files
     * @param array<string, ImageUploadProfileConfig> $images
     */
    public function __construct(
        public readonly array $files,
        public readonly array $images,
    ) {}
}
