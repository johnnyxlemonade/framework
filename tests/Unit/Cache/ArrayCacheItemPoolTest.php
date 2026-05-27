<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use DateTimeImmutable;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Lemonade\Framework\Cache\Store\ArrayCacheItemPool;
use Lemonade\Framework\Cache\Store\CacheItem;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;

final class ArrayCacheItemPoolTest extends TestCase
{
    public function testGetItemMissReturnsNonHitItem(): void
    {
        $pool = new ArrayCacheItemPool();
        $item = $pool->getItem('missing');

        self::assertFalse($item->isHit());
    }

    public function testSaveStoresItemAndGetItemReturnsHitWithValue(): void
    {
        $pool = new ArrayCacheItemPool();
        $item = (new CacheItem('k'))->set('v');

        self::assertTrue($pool->save($item));
        self::assertTrue($pool->getItem('k')->isHit());
        self::assertSame('v', $pool->getItem('k')->get());
    }

    public function testHasDeleteDeleteItemsAndClearWork(): void
    {
        $pool = new ArrayCacheItemPool();
        $pool->save((new CacheItem('a'))->set('1'));
        $pool->save((new CacheItem('b'))->set('2'));

        self::assertTrue($pool->hasItem('a'));
        self::assertTrue($pool->deleteItem('a'));
        self::assertFalse($pool->hasItem('a'));

        self::assertTrue($pool->deleteItems(['b']));
        self::assertFalse($pool->hasItem('b'));

        $pool->saveDeferred((new CacheItem('deferred'))->set('x'));
        self::assertTrue($pool->clear());
        self::assertFalse($pool->hasItem('deferred'));
    }

    public function testSaveDeferredAndCommit(): void
    {
        $pool = new ArrayCacheItemPool();
        $item = (new CacheItem('deferred'))->set('v');

        self::assertTrue($pool->saveDeferred($item));
        self::assertFalse($pool->hasItem('deferred'));
        self::assertTrue($pool->commit());
        self::assertTrue($pool->hasItem('deferred'));
    }

    public function testExpiredItemBecomesMissAndIsRemoved(): void
    {
        $pool = new ArrayCacheItemPool();
        $item = (new CacheItem('exp', 'v', true))->expiresAt(new DateTimeImmutable('-1 second'));
        $pool->save($item);

        $read = $pool->getItem('exp');
        self::assertFalse($read->isHit());
        self::assertFalse($pool->hasItem('exp'));
    }

    public function testSaveReturnsFalseForForeignCacheItemImplementation(): void
    {
        $pool = new ArrayCacheItemPool();

        self::assertFalse($pool->save(new ForeignCacheItem('foreign')));
    }

    public function testInvalidCacheKeyThrows(): void
    {
        $pool = new ArrayCacheItemPool();

        $this->expectException(InvalidCacheKeyException::class);
        $pool->getItem('bad:key');
    }
}

final class ForeignCacheItem implements CacheItemInterface
{
    public function __construct(private readonly string $key) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return null;
    }

    public function isHit(): bool
    {
        return false;
    }

    public function set(mixed $value): static
    {
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
