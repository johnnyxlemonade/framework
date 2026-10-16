<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

/**
 * Carries the resolved file store location, key namespace prefix, and default lifetime.
 */
final readonly class CacheFileStoreConfig
{
    /**
     * Captures immutable settings for the file-backed cache store.
     */
    public function __construct(
        public string $path,
        public string $prefix,
        public int $ttl,
    ) {
    }
}
