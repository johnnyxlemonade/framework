<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use Lemonade\Framework\Cache\CacheKeyValidator;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class NullCacheItemPool implements CacheItemPoolInterface
{
    /**
     * @throws InvalidCacheKeyException
     */
    public function getItem(string $key): CacheItemInterface
    {
        CacheKeyValidator::assertValid($key);

        return new CacheItem($key);
    }

    /**
     * @throws InvalidCacheKeyException
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function hasItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        return false;
    }

    public function clear(): bool
    {
        return true;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function deleteItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        return true;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            CacheKeyValidator::assertValid($key);
        }

        return true;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function save(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        return true;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        return true;
    }

    public function commit(): bool
    {
        return true;
    }
}
