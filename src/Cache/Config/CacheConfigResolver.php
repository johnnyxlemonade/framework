<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Config;

final class CacheConfigResolver
{
    public function resolve(CacheConfigDefinition ...$definitions): CacheConfig
    {
        $defaultStore = 'file';
        $filePath = 'cache/framework';
        $filePrefix = 'lemonade';
        $fileTtl = 300;

        foreach ($definitions as $definition) {
            $data = $definition->toArray();
            if (array_key_exists('default', $data)) {
                $defaultStore = $this->stringOr($data['default'], $defaultStore);
            }

            $stores = is_array($data['stores'] ?? null) ? $data['stores'] : [];
            $file = is_array($stores['file'] ?? null) ? $stores['file'] : [];

            if (array_key_exists('path', $file)) {
                $filePath = $this->stringOr($file['path'], $filePath);
            }
            if (array_key_exists('prefix', $file)) {
                $filePrefix = $this->stringOr($file['prefix'], $filePrefix);
            }
            if (array_key_exists('ttl', $file)) {
                $fileTtl = max(1, $this->intOr($file['ttl'], $fileTtl));
            }
        }

        return new CacheConfig($defaultStore, new CacheFileStoreConfig($filePath, $filePrefix, $fileTtl));
    }

    private function stringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
    }

    private function intOr(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
