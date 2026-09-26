<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final readonly class SessionRedisConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public int $database,
        public ?string $password,
        public string $prefix,
        public float $timeout,
    ) {}
}
