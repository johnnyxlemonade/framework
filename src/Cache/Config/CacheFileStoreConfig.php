<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

final class CacheFileStoreConfig
{
    public function __construct(
        public readonly string $path,
        public readonly string $prefix,
        public readonly int $ttl,
    ) {}
}
