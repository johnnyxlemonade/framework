<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

final readonly class CacheFileStoreConfig
{
    public function __construct(
        public string $path,
        public string $prefix,
        public int $ttl,
    ) {
    }
}
