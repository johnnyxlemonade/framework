<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cache\Store;

use DateTimeImmutable;
use Lemonade\Framework\Cache\CacheKeyValidator;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

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
        return $this->getItem($key)->isHit();
    }

    public function clear(): bool
    {
        $this->items = [];
        $this->deferred = [];

        return true;
    }

    /**
     * @throws InvalidCacheKeyException
     */
    public function deleteItem(string $key): bool
    {
        CacheKeyValidator::assertValid($key);

        unset($this->items[$key], $this->deferred[$key]);

        return true;
    }

    /**
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
