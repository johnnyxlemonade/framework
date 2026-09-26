<?php

declare(strict_types=1);

namespace Lemonade\Framework\Session\Config;

final readonly class SessionConfig
{
    public function __construct(
        public string $driver,
        public string $cookie,
        public int $lifetime,
        public SessionNativeConfig $native,
        public SessionFileConfig $file,
        public SessionDatabaseConfig $database,
        public SessionRedisConfig $redis,
    ) {}
}
