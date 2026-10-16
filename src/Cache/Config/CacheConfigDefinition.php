<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds one ordered cache configuration contribution for the typed config pipeline.
 */
final class CacheConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Starts an empty contribution that can override selected cache settings.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the registry key shared by cache configuration sources.
     */
    public static function moduleKey(): string
    {
        return 'cache';
    }

    /**
     * Selects the store bound as the generic cache pool.
     */
    public function defaultStore(string $store): self
    {
        return $this->set('default', $store);
    }

    /**
     * Records the file store path, key prefix, and default lifetime in seconds.
     */
    public function fileStore(string $path, string $prefix = 'lemonade', int $ttl = 300): self
    {
        return $this
            ->set('stores.file.driver', 'file')
            ->set('stores.file.path', $path)
            ->set('stores.file.prefix', $prefix)
            ->set('stores.file.ttl', $ttl);
    }
}
