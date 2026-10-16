<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use DateTimeImmutable;
use Lemonade\Framework\Cache\CacheKeyValidator;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Stores PSR-6 cache entries in request-local memory with lazy expiration.
 *
 * Deferred entries become visible only after commit, and no state survives the pool instance.
 */
final class ArrayCacheItemPool implements CacheItemPoolInterface
{
    /**
     * @var array<string, array{value:mixed, expires_at:int|null}>
     */
    private array $items = [];

    /**
     * @var array<string, CacheItem>
     */
    private array $deferred = [];

    /**
     * Returns a hit for a live entry or a new miss item while removing expired state.
     *
     * @throws InvalidCacheKeyException
     */
    public function getItem(string $key): CacheItemInterface
    {
        CacheKeyValidator::assertValid($key);

        if (!isset($this->items[$key])) {
            return new CacheItem($key);
        }

        $stored = $this->items[$key];
        $expiresAt = $stored['expires_at'];

        if ($expiresAt !== null && $expiresAt <= time()) {
            unset($this->items[$key]);

            return new CacheItem($key);
        }

        return new CacheItem(
            key: $key,
            value: $stored['value'],
            hit: true,
            expiresAt: $expiresAt === null ? null : (new DateTimeImmutable())->setTimestamp($expiresAt),
        );
    }

    /**
     * Returns requested entries keyed by their original cache keys.
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
     * Reports whether a live entry exists for the validated key.
     *
     * @throws InvalidCacheKeyException
     */
    public function hasItem(string $key): bool
    {
        return $this->getItem($key)->isHit();
    }

    /**
     * Removes all stored and deferred entries from this pool instance.
     */
    public function clear(): bool
    {
        $this->items = [];
        $this->deferred = [];

        return true;
    }

    /**
     * Removes one stored or deferred entry and succeeds when it is already absent.
     *
     * @throws InvalidCacheKeyException
     */
    public function deleteItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        unset($this->items[$key], $this->deferred[$key]);

        return true;
    }

    /**
     * Removes each requested entry and succeeds when all keys are valid.
     *
     * @throws InvalidCacheKeyException
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    /**
     * Stores a framework cache item immediately and rejects foreign item implementations.
     *
     * @throws InvalidCacheKeyException
     */
    public function save(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        if (!($item instanceof CacheItem)) {
            return false;
        }

        $expiresAt = $item->expiresAtDate();

        $this->items[$item->getKey()] = [
            'value' => $item->get(),
            'expires_at' => $expiresAt?->getTimestamp(),
        ];

        unset($this->deferred[$item->getKey()]);

        return true;
    }

    /**
     * Queues a framework cache item for a later commit without making it visible yet.
     *
     * @throws InvalidCacheKeyException
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        CacheKeyValidator::assertValid($item->getKey());

        if (!($item instanceof CacheItem)) {
            return false;
        }

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * Persists every deferred entry and reports whether all saves succeeded.
     */
    public function commit(): bool
    {
        $ok = true;

        foreach ($this->deferred as $key => $item) {
            if (!$this->save($item)) {
                $ok = false;
            }

            unset($this->deferred[$key]);
        }

        return $ok;
    }
}
