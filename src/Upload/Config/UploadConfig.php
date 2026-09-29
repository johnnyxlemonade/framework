<?php

declare(strict_types=1);

namespace Lemonade\Framework\Upload\Config;

final readonly class UploadConfig
{
    /**
     * @param array<string, FileUploadProfileConfig> $files
     * @param array<string, ImageUploadProfileConfig> $images
     */
    public function __construct(
        public array $files,
        public array $images,
    ) {
    }
}
