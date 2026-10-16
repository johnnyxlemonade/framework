<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;

/**
 * Merges ordered cache definitions into one normalized immutable runtime configuration.
 */
final class CacheConfigResolver
{
    /**
     * Applies later contributions over earlier values while preserving canonical fallbacks.
     */
    public function resolve(CacheConfigDefinition ...$definitions): CacheConfig
    {
        $defaultStore = 'file';
        $filePath = 'cache/framework';
        $filePrefix = 'lemonade';
        $fileTtl = 300;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            if (array_key_exists('default', $data)) {
                $defaultStore = ConfigValueNormalizer::nonEmptyStringOr($data['default'], $defaultStore);
            }

            $stores = is_array($data['stores'] ?? null) ? $data['stores'] : [];
            $file = is_array($stores['file'] ?? null) ? $stores['file'] : [];

            if (array_key_exists('path', $file)) {
                $filePath = ConfigValueNormalizer::nonEmptyStringOr($file['path'], $filePath);
            }
            if (array_key_exists('prefix', $file)) {
                $filePrefix = ConfigValueNormalizer::nonEmptyStringOr($file['prefix'], $filePrefix);
            }
            if (array_key_exists('ttl', $file)) {
                $fileTtl = max(1, ConfigValueNormalizer::intOr($file['ttl'], $fileTtl));
            }
        }

        return new CacheConfig($defaultStore, new CacheFileStoreConfig($filePath, $filePrefix, $fileTtl));
    }

}
