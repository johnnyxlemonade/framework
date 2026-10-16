<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Request;

use Lemonade\Framework\Http\Request\ServerHelper;
use PHPUnit\Framework\TestCase;

final class ServerHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];

        parent::tearDown();
    }

    public function testHttpsFlagUsesCanonicalDirectServerValues(): void
    {
        self::assertFalse(ServerHelper::isHttps());

        $_SERVER['HTTPS'] = 'on';
        self::assertTrue(ServerHelper::isHttps());

        $_SERVER['HTTPS'] = ' OFF ';
        self::assertFalse(ServerHelper::isHttps());

        $_SERVER['HTTPS'] = '0';
        self::assertFalse(ServerHelper::isHttps());
    }

    public function testHttpsDetectionAcceptsTheCanonicalServerPortValue(): void
    {
        $_SERVER['SERVER_PORT'] = '443';

        self::assertTrue(ServerHelper::isHttps());
    }

    public function testHostResolutionUsesCanonicalPriorityAndFallback(): void
    {
        self::assertSame('localhost', ServerHelper::host());
        self::assertSame('fallback.example', ServerHelper::host('fallback.example'));

        $_SERVER['SERVER_NAME'] = ' name.example ';
        self::assertSame('name.example', ServerHelper::host());

        $_SERVER['HTTP_HOST'] = ' host.example:8443 ';
        self::assertSame('host.example:8443', ServerHelper::host());
    }

    public function testSchemeUsesForwardedProtocolBeforeDirectServerMetadata(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = ' HTTP, https ';

        self::assertSame('http', ServerHelper::scheme());

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = ' HTTPS, http ';
        self::assertSame('https', ServerHelper::scheme());
    }

    public function testSchemeFallsBackToDirectHttpsForUnknownForwardedProtocol(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'websocket';

        self::assertSame('https', ServerHelper::scheme());
    }
}
