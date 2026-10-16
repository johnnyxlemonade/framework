<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

/**
 * Carries the resolved default cache store and its backend-specific settings.
 */
final readonly class CacheConfig
{
    /**
     * Captures immutable cache configuration consumed by cache service wiring.
     */
    public function __construct(
        private string $defaultStore,
        private CacheFileStoreConfig $fileStore,
    ) {
    }

    /**
     * Returns the store selected for the generic cache pool binding.
     */
    public function defaultStore(): string
    {
        return $this->defaultStore;
    }

    /**
     * Returns the resolved file-backed store settings.
     */
    public function fileStore(): CacheFileStoreConfig
    {
        return $this->fileStore;
    }
}
