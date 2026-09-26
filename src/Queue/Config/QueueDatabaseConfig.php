<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

final readonly class QueueDatabaseConfig
{
    public function __construct(
        public string $table,
        public string $failedTable,
    ) {}
}
