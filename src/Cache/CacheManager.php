<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

use function max;

final class CacheManager
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
    ) {}

    public function pool(): CacheItemPoolInterface
    {
        return $this->pool;
    }

    /**
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
     * @throws InvalidArgumentException
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->pool->getItem($key);

        return $item->isHit() ? $item->get() : $default;
    }

    /**
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
     * @throws InvalidArgumentException
     */
    public function has(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function forget(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

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
