<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final class SessionRedisConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly int $database,
        public readonly ?string $password,
        public readonly string $prefix,
        public readonly float $timeout,
    ) {}
}
