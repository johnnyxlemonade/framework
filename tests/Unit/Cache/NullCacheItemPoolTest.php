<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use Lemonade\Framework\Cache\Store\NullCacheItemPool;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;

final class NullCacheItemPoolTest extends TestCase
{
    public function testGetItemAlwaysMissAndHasItemAlwaysFalse(): void
    {
        $pool = new NullCacheItemPool();

        self::assertFalse($pool->getItem('key')->isHit());
        self::assertFalse($pool->hasItem('key'));
    }

    public function testMutatingOperationsReturnTrue(): void
    {
        $pool = new NullCacheItemPool();
        $item = new NullPoolForeignCacheItem('key');

        self::assertTrue($pool->save($item));
        self::assertTrue($pool->saveDeferred($item));
        self::assertTrue($pool->deleteItem('key'));
        self::assertTrue($pool->deleteItems(['key1', 'key2']));
        self::assertTrue($pool->clear());
        self::assertTrue($pool->commit());
    }

    public function testInvalidCacheKeyThrows(): void
    {
        $pool = new NullCacheItemPool();

        $this->expectException(InvalidCacheKeyException::class);
        $pool->getItem('bad:key');
    }

    public function testSaveValidatesKeyBeforeReturningTrue(): void
    {
        $pool = new NullCacheItemPool();

        $this->expectException(InvalidCacheKeyException::class);
        $pool->save(new NullPoolForeignCacheItem('bad:key'));
    }
}

final class NullPoolForeignCacheItem implements CacheItemInterface
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
