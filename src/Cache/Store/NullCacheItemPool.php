<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use Lemonade\Framework\Cache\CacheKeyValidator;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Implements a validating PSR-6 sink where reads always miss and mutations always succeed.
 *
 * No cache values or deferred operations are retained.
 */
final class NullCacheItemPool implements CacheItemPoolInterface
{
    /**
     * Returns a new miss item for every valid key.
     *
     * @throws InvalidCacheKeyException
     */
    public function getItem(string $key): CacheItemInterface
    {
        CacheKeyValidator::assertValid($key);

        return new CacheItem($key);
    }

    /**
     * Returns new miss items keyed by their original cache keys.
     *
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
     * Validates the key and always reports that no entry exists.
     *
     * @throws InvalidCacheKeyException
     */
    public function hasItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        return false;
    }

    /**
     * Succeeds without retaining or removing any state.
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * Validates one key and succeeds without retaining state.
     *
     * @throws InvalidCacheKeyException
     */
    public function deleteItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        return true;
    }

    /**
     * Validates every key and succeeds without retaining state.
     *
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
     * Validates the item key and discards its value.
     *
     * @throws InvalidCacheKeyException
     */
    public function save(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        return true;
    }

    /**
     * Validates the item key and discards the deferred operation.
     *
     * @throws InvalidCacheKeyException
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        return true;
    }

    /**
     * Succeeds because this pool never retains deferred operations.
     */
    public function commit(): bool
    {
        return true;
    }
}
