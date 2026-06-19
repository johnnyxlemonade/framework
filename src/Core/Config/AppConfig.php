<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

final class AppConfig
{
    public function __construct(
        public readonly ?string $timezone,
        public readonly ?string $baseUrl,
        public readonly string $basePath,
        public readonly string $env,
        public readonly bool $debug,
        public readonly string $appPath,
        public readonly string $configPath,
        public readonly string $storagePath,
    ) {}
}
