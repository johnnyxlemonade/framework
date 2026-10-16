<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cache;

use Lemonade\Framework\Cache\Config\CacheConfigDefinition;
use Lemonade\Framework\Cache\Config\CacheConfigResolver;
use PHPUnit\Framework\TestCase;

final class CacheConfigResolverTest extends TestCase
{
    public function testResolvedConfigurationIsExposedThroughTypedGetters(): void
    {
        $config = (new CacheConfigResolver())->resolve(
            CacheConfigDefinition::create()
                ->defaultStore('file')
                ->fileStore('cache/application', 'application', 600),
        );

        self::assertSame('file', $config->defaultStore());
        self::assertSame('cache/application', $config->fileStore()->path);
        self::assertSame('application', $config->fileStore()->prefix);
        self::assertSame(600, $config->fileStore()->ttl);
    }
}
