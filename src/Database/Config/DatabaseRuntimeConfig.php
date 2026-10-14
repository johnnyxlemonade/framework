<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Config;

use Lemonade\Framework\Database\Connection\DatabaseConfig;

/**
 * Holds the selected default connection and all resolved named connection configurations.
 */
final readonly class DatabaseRuntimeConfig
{
    /**
     * Captures the resolved default connection and the complete named connection map.
     *
     * @param array<string, DatabaseConfig> $connections
     */
    public function __construct(
        public ?string $defaultConnection,
        public array $connections,
    ) {
    }
}
