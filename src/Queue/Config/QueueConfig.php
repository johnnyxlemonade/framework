<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

final class QueueConfig
{
    /**
     * @param list<string> $transports
     * @param array<string, mixed> $handlers
     */
    public function __construct(
        public readonly string $defaultTransport,
        public readonly array $transports,
        public readonly array $handlers,
        public readonly QueueDatabaseConfig $database,
    ) {}
}
