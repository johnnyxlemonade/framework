<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use DateInterval;
use DateTimeImmutable;
use Lemonade\Framework\Cache\Store\CacheItem;
use PHPUnit\Framework\TestCase;

final class CacheItemTest extends TestCase
{
    public function testGetKeyReturnsKeyAndNewItemIsMiss(): void
    {
        $item = new CacheItem('key-1');

        self::assertSame('key-1', $item->getKey());
        self::assertFalse($item->isHit());
    }

    public function testSetStoresValueAndReturnsSelf(): void
    {
        $item = new CacheItem('key-2');

        $result = $item->set('value');

        self::assertSame($item, $result);
        self::assertSame('value', $item->get());
    }

    public function testExpiresAtNullAndExpiresAfterNullSetNoExpiration(): void
    {
        $item = new CacheItem('key-3', 'value', true);

        $item->expiresAt(new DateTimeImmutable('+1 hour'));
        $item->expiresAt(null);

        self::assertNull($item->expiresAtDate());
        self::assertTrue($item->isHit());

        $item->expiresAfter(new DateInterval('PT1H'));
        $item->expiresAfter(null);

        self::assertNull($item->expiresAtDate());
        self::assertTrue($item->isHit());
    }

    public function testExpiresAfterIntSetsExpiration(): void
    {
        $item = new CacheItem('key-4', 'value', true);

        $item->expiresAfter(30);
        $expiresAt = $item->expiresAtDate();

        self::assertInstanceOf(DateTimeImmutable::class, $expiresAt);
        self::assertGreaterThan(time(), $expiresAt->getTimestamp());
    }

    public function testExpiresAfterDateIntervalSetsExpiration(): void
    {
        $item = new CacheItem('key-5', 'value', true);

        $item->expiresAfter(new DateInterval('PT10M'));
        $expiresAt = $item->expiresAtDate();

        self::assertInstanceOf(DateTimeImmutable::class, $expiresAt);
        self::assertGreaterThan(time(), $expiresAt->getTimestamp());
    }

    public function testExpiredHitReturnsFalse(): void
    {
        $item = new CacheItem(
            key: 'key-6',
            value: 'value',
            hit: true,
            expiresAt: new DateTimeImmutable('-1 second'),
        );

        self::assertFalse($item->isHit());
    }
}
