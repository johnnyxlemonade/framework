<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final class SessionConfig
{
    public function __construct(
        public readonly string $driver,
        public readonly string $cookie,
        public readonly int $lifetime,
        public readonly SessionNativeConfig $native,
        public readonly SessionFileConfig $file,
        public readonly SessionDatabaseConfig $database,
        public readonly SessionRedisConfig $redis,
    ) {}
}
