<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

final readonly class CacheConfig
{
    public function __construct(
        public string $defaultStore,
        public CacheFileStoreConfig $fileStore,
    ) {}
}
