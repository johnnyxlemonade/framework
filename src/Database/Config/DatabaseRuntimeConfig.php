<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Config;

use Lemonade\Framework\Database\Connection\DatabaseConfig;

final readonly class DatabaseRuntimeConfig
{
    /**
     * @param array<string, DatabaseConfig> $connections
     */
    public function __construct(
        public ?string $defaultConnection,
        public array $connections,
    ) {
    }
}
