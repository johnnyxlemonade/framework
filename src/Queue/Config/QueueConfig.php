<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Config;

final readonly class QueueConfig
{
    /**
     * @param list<string> $transports
     * @param array<string, mixed> $handlers
     */
    public function __construct(
        public string $defaultTransport,
        public array $transports,
        public array $handlers,
        public QueueDatabaseConfig $database,
    ) {}
}
