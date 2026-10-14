<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session\Config;

use Lemonade\Framework\Session\Config\SessionConfigDefinition;
use Lemonade\Framework\Session\Config\SessionConfigResolver;
use PHPUnit\Framework\TestCase;

final class SessionConfigResolverTest extends TestCase
{
    public function testItResolvesCanonicalFrameworkDefaultsWithoutDefinitions(): void
    {
        $config = (new SessionConfigResolver())->resolve();

        self::assertSame('native', $config->driver());
        self::assertSame('LEMONADE_SESSION', $config->cookie());
        self::assertSame(7200, $config->lifetime());
        self::assertSame('sessions', $config->native()->path());
        self::assertSame('sessions', $config->file()->path());
        self::assertSame('sessions', $config->database()->table());
        self::assertSame('127.0.0.1', $config->redis()->host());
        self::assertSame(6379, $config->redis()->port());
        self::assertSame(0, $config->redis()->database());
        self::assertNull($config->redis()->password());
        self::assertSame('sess:', $config->redis()->prefix());
        self::assertSame(2.5, $config->redis()->timeout());
    }

    public function testItPreservesAbsentFieldsFromEarlierContributions(): void
    {
        $config = (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::create()
                ->cookie('FIRST_SESSION')
                ->redisHost('redis.internal')
                ->redisPassword('secret'),
            SessionConfigDefinition::create()->lifetime(3600),
        );

        self::assertSame('FIRST_SESSION', $config->cookie());
        self::assertSame(3600, $config->lifetime());
        self::assertSame('redis.internal', $config->redis()->host());
        self::assertSame('secret', $config->redis()->password());
    }

    public function testItAllowsAnExplicitNullRedisPasswordToClearThePreviousValue(): void
    {
        $config = (new SessionConfigResolver())->resolve(
            SessionConfigDefinition::create()->redisPassword('secret'),
            SessionConfigDefinition::create()->redisPassword(null),
        );

        self::assertNull($config->redis()->password());
    }
}
