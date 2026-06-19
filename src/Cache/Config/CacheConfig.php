<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

final class CacheConfig
{
    public function __construct(
        public readonly string $defaultStore,
        public readonly CacheFileStoreConfig $fileStore,
    ) {}
}
