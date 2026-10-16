<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

use function max;

/**
 * Provides cache-aside convenience operations over one PSR-6 cache pool.
 *
 * The manager keeps no cache state of its own and delegates persistence and invalidation to the pool.
 */
final readonly class CacheManager
{
    /**
     * Binds convenience operations to the pool that owns cached state.
     */
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    /**
     * Exposes the underlying pool for operations outside the manager's convenience API.
     */
    public function pool(): CacheItemPoolInterface
    {
        return $this->pool;
    }

    /**
     * Returns a cached value or computes and stores it with the requested lifetime.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws InvalidArgumentException
     */
    public function remember(string $key, ?int $ttlSeconds, callable $callback): mixed
    {
        $item = $this->pool->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        $value = $callback();

        $item->set($value);
        $this->applyExpiration($item, $ttlSeconds);

        $this->pool->save($item);

        return $value;
    }

    /**
     * Returns a cached value or computes and stores it without expiration.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws InvalidArgumentException
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    /**
     * Returns the cached value for a hit and the supplied default for a miss.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * Stores a value and clears expiration with null or clamps a non-positive lifetime to immediate expiry.
     *
     * @throws InvalidArgumentException
     */
    public function put(string $key, mixed $value, ?int $ttlSeconds = null): bool
    {
        $item = $this->pool->getItem($key);

        $item->set($value);
        $this->applyExpiration($item, $ttlSeconds);

        return $this->pool->save($item);
    }

    /**
     * Reports whether the underlying pool contains a live item for the key.
     *
     * @throws InvalidArgumentException
     */
    public function has(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    /**
     * Removes one key through the underlying pool.
     *
     * @throws InvalidArgumentException
     */
    public function forget(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    /**
     * Removes all entries owned by the underlying pool.
     */
    public function clear(): bool
    {
        return $this->pool->clear();
    }

    private function applyExpiration(CacheItemInterface $item, ?int $ttlSeconds): void
    {
        if ($ttlSeconds === null) {
            $item->expiresAt(null);

            return;
        }

        $item->expiresAfter(max(0, $ttlSeconds));
    }
}
