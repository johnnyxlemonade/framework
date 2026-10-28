<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Health;

use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Health\FrameworkHealthConfigSnapshot;
use PHPUnit\Framework\TestCase;

final class FrameworkHealthConfigSnapshotTest extends TestCase
{
    public function testExposesImmutableStateThroughGetters(): void
    {
        $api = (new ApiConfigResolver())->resolve();
        $app = new AppConfig(
            timezone: 'Europe/Prague',
            baseUrl: 'https://example.test',
            basePath: '/app',
            publicPath: '/app/public',
            env: 'testing',
            debug: false,
            appPath: '/app/app',
            configPath: '/app/app/Config',
            storagePath: '/app/storage',
        );
        $snapshot = new FrameworkHealthConfigSnapshot(
            api: $api,
            app: $app,
            cors: null,
        );

        self::assertSame($api, $snapshot->api());
        self::assertSame($app, $snapshot->app());
        self::assertNull($snapshot->cors());
        self::assertSame([], get_object_vars($snapshot));
    }
}
