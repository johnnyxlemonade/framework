<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

final readonly class AppConfig
{
    public function __construct(
        public ?string $timezone,
        public ?string $baseUrl,
        public string $basePath,
        public string $publicPath,
        public string $env,
        public bool $debug,
        public string $appPath,
        public string $configPath,
        public string $storagePath,
    ) {}
}
