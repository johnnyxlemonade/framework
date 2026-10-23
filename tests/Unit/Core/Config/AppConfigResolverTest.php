<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\AppConfigResolver;
use PHPUnit\Framework\TestCase;

final class AppConfigResolverTest extends TestCase
{
    public function testLaterDefinitionOverridesTheApplicationSecret(): void
    {
        $config = (new AppConfigResolver())->resolve(
            AppConfigDefinition::create()->secret('first-secret'),
            AppConfigDefinition::create()->secret('second-secret'),
        );

        self::assertSame('second-secret', $config->secret());
    }

    public function testEmptyApplicationSecretResolvesToNull(): void
    {
        $config = (new AppConfigResolver())->resolve(
            AppConfigDefinition::create()->secret('   '),
        );

        self::assertNull($config->secret());
    }

    public function testMergePreservesTypedFallbacksAndAllowsNullableValuesToReset(): void
    {
        $config = (new AppConfigResolver())->resolve(
            AppConfigDefinition::fromArrayData([
                'timezone' => 'Europe/Prague',
                'base_url' => 'https://first.example.test',
                'secret' => 'first-secret',
                'base_path' => '/first',
                'public_path' => '/first/public',
                'env' => 'development',
                'debug' => true,
                'app_path' => '/first/app',
                'config_path' => '/first/config',
                'storage_path' => '/first/storage',
            ]),
            AppConfigDefinition::fromArrayData([
                'timezone' => null,
                'base_url' => null,
                'secret' => '   ',
                'base_path' => [],
                'public_path' => ' /second/public ',
                'env' => null,
                'debug' => 'not-a-boolean',
                'app_path' => false,
                'config_path' => '/second/config',
                'storage_path' => [],
            ]),
        );

        self::assertNull($config->timezone());
        self::assertNull($config->baseUrl());
        self::assertNull($config->secret());
        self::assertSame('/first', $config->basePath());
        self::assertSame('/second/public', $config->publicPath());
        self::assertSame('development', $config->env());
        self::assertTrue($config->debug());
        self::assertSame('/first/app', $config->appPath());
        self::assertSame('/second/config', $config->configPath());
        self::assertSame('/first/storage', $config->storagePath());
    }

    public function testInvalidBaseUrlTypeIsRejectedInsteadOfTreatedAsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('App base_url must be a string or null.');

        (new AppConfigResolver())->resolve(
            AppConfigDefinition::fromArrayData(['base_url' => []]),
        );
    }

    public function testBaseUrlWhitespaceIsRejectedInsteadOfTrimmed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('App base_url must be an absolute');

        (new AppConfigResolver())->resolve(
            AppConfigDefinition::create()->baseUrl(' https://example.test '),
        );
    }
}
