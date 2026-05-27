<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use Lemonade\Framework\Cache\CacheKeyValidator;
use Lemonade\Framework\Cache\Exception\InvalidCacheKeyException;
use PHPUnit\Framework\TestCase;

final class CacheKeyValidatorTest extends TestCase
{
    public function testValidKeyPasses(): void
    {
        CacheKeyValidator::assertValid('valid-key_123');
        self::addToAssertionCount(1);
    }

    public function testEmptyKeyThrows(): void
    {
        $this->expectException(InvalidCacheKeyException::class);
        CacheKeyValidator::assertValid('');
    }

    public function testReservedCharactersThrow(): void
    {
        $this->expectException(InvalidCacheKeyException::class);
        CacheKeyValidator::assertValid('bad:key');
    }
}
