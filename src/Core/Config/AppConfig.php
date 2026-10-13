<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

/**
 * Carries application-wide runtime values resolved from host configuration and bootstrap context.
 */
final readonly class AppConfig
{
    /**
     * Captures immutable host configuration consumed by framework capabilities.
     */
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
        public ?string $secret = null,
    ) {
    }
}
