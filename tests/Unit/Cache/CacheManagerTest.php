<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use Lemonade\Framework\Cache\CacheManager;
use Lemonade\Framework\Cache\Store\ArrayCacheItemPool;
use PHPUnit\Framework\TestCase;

final class CacheManagerTest extends TestCase
{
    public function testPoolReturnsOriginalPool(): void
    {
        $pool = new ArrayCacheItemPool();
        $manager = new CacheManager($pool);

        self::assertSame($pool, $manager->pool());
    }

    public function testRememberMissCallsCallbackStoresAndReturnsValue(): void
    {
        $manager = new CacheManager(new ArrayCacheItemPool());
        $calls = 0;

        $value = $manager->remember('k', 60, static function () use (&$calls): string {
            $calls++;
            return 'v';
        });

        self::assertSame('v', $value);
        self::assertSame(1, $calls);
        self::assertSame('v', $manager->get('k'));
    }

    public function testRememberHitDoesNotCallCallback(): void
    {
        $manager = new CacheManager(new ArrayCacheItemPool());
        $manager->put('k', 'existing', 60);
        $calls = 0;

        $value = $manager->remember('k', 60, static function () use (&$calls): string {
            $calls++;
            return 'new';
        });

        self::assertSame('existing', $value);
        self::assertSame(0, $calls);
    }

    public function testRememberForeverStoresWithoutExpiration(): void
    {
        $manager = new CacheManager(new ArrayCacheItemPool());
        $value = $manager->rememberForever('forever', static fn(): string => 'ok');

        self::assertSame('ok', $value);
        self::assertSame('ok', $manager->get('forever'));
    }

    public function testGetReturnsDefaultOnMiss(): void
    {
        $manager = new CacheManager(new ArrayCacheItemPool());

        self::assertSame('default', $manager->get('missing', 'default'));
    }

    public function testPutHasForgetAndClear(): void
    {
        $manager = new CacheManager(new ArrayCacheItemPool());

        self::assertTrue($manager->put('k', 'v', 120));
        self::assertTrue($manager->has('k'));
        self::assertTrue($manager->forget('k'));
        self::assertFalse($manager->has('k'));

        $manager->put('x', 'y', 120);
        self::assertTrue($manager->clear());
        self::assertFalse($manager->has('x'));
    }

    public function testNegativeTtlIsNormalizedToZero(): void
    {
        $manager = new CacheManager(new ArrayCacheItemPool());
        $manager->put('neg', 'value', -10);

        self::assertFalse($manager->has('neg'));
        self::assertSame('default', $manager->get('neg', 'default'));
    }
}
