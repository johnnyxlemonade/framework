<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

final class QueueDatabaseConfig
{
    public function __construct(
        public readonly string $table,
        public readonly string $failedTable,
    ) {}
}
