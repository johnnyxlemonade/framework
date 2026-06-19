<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Config;

use Lemonade\Framework\Database\Connection\DatabaseConfig;

final class DatabaseRuntimeConfig
{
    /**
     * @param array<string, DatabaseConfig> $connections
     */
    public function __construct(
        public readonly ?string $defaultConnection,
        public readonly array $connections,
    ) {}
}
