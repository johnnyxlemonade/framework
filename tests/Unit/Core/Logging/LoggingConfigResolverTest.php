<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Logging;

use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigResolver;
use PHPUnit\Framework\TestCase;

final class LoggingConfigResolverTest extends TestCase
{
    public function testDefaultsToSharedSevenDayRetentionAndDisabledOptionalChannels(): void
    {
        $config = (new LoggingConfigResolver())->resolve(LoggingConfigDefinition::create());

        self::assertSame(7, $config->retentionDays);
        self::assertFalse($config->requestEnabled);
        self::assertSame(0, $config->requestMinStatus);
        self::assertFalse($config->benchmarkEnabled);
    }

    public function testResolvesCanonicalLoggingPolicy(): void
    {
        $config = (new LoggingConfigResolver())->resolve(
            LoggingConfigDefinition::create()
                ->retentionDays(14)
                ->requestEnabled()
                ->requestMinStatus(400)
                ->benchmarkEnabled(),
        );

        self::assertSame(14, $config->retentionDays);
        self::assertTrue($config->requestEnabled);
        self::assertSame(400, $config->requestMinStatus);
        self::assertTrue($config->benchmarkEnabled);
    }

    public function testLegacyLoggingKeysDoNotChangeTheCanonicalContract(): void
    {
        $legacy = LoggingConfigDefinition::fromArrayData([
            'app' => ['enabled' => false, 'path' => 'elsewhere.log', 'days' => 99, 'level' => 'debug'],
            'error' => ['enabled' => false, 'not_found' => true],
            'request' => ['path' => 'elsewhere.log', 'days' => 99, 'level' => 'debug'],
            'benchmark' => ['path' => 'elsewhere.log', 'days' => 99, 'level' => 'debug'],
        ]);

        $config = (new LoggingConfigResolver())->resolve($legacy);

        self::assertSame(7, $config->retentionDays);
        self::assertFalse($config->requestEnabled);
        self::assertSame(0, $config->requestMinStatus);
        self::assertFalse($config->benchmarkEnabled);
    }
}
