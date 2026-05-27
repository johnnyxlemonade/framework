<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Support\BaseUrlResolver;
use PHPUnit\Framework\TestCase;

final class BaseUrlResolverTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
    }

    public function testConfiguredHttpsBaseUrlIsUsedAndTrailingSlashTrimmed(): void
    {
        $resolver = new BaseUrlResolver(new Config([
            'app' => [
                'base_url' => 'https://example.com/',
            ],
        ]));

        self::assertSame('https://example.com', $resolver->baseUrl());
    }

    public function testConfiguredBaseUrlWithoutSchemeGetsSchemeFromServerContext(): void
    {
        $_SERVER['HTTPS'] = 'on';

        $resolver = new BaseUrlResolver(new Config([
            'app' => [
                'base_url' => 'example.com',
            ],
        ]));

        self::assertSame('https://example.com', $resolver->baseUrl());
    }

    public function testBaseUrlJoinsPathCorrectly(): void
    {
        $resolver = new BaseUrlResolver(new Config([
            'app' => [
                'base_url' => 'https://example.com',
            ],
        ]));

        self::assertSame('https://example.com/path/to/page', $resolver->baseUrl('path/to/page'));
    }

    public function testForwardedProtoHttpsForcesHttpsScheme(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_HOST'] = 'proxy.example';

        $resolver = new BaseUrlResolver(new Config());

        self::assertSame('https://proxy.example', $resolver->baseUrl());
    }

    public function testHttpsOnForcesHttpsScheme(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'secure.example';

        $resolver = new BaseUrlResolver(new Config());

        self::assertSame('https://secure.example', $resolver->baseUrl());
    }

    public function testPort443ForcesHttpsScheme(): void
    {
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['HTTP_HOST'] = 'port.example';

        $resolver = new BaseUrlResolver(new Config());

        self::assertSame('https://port.example', $resolver->baseUrl());
    }

    public function testHttpHostHasPriorityOverServerName(): void
    {
        $_SERVER['HTTP_HOST'] = 'host.example';
        $_SERVER['SERVER_NAME'] = 'name.example';

        $resolver = new BaseUrlResolver(new Config());

        self::assertSame('http://host.example', $resolver->baseUrl());
    }

    public function testFallbackHostIsLocalhost(): void
    {
        $resolver = new BaseUrlResolver(new Config());

        self::assertSame('http://localhost', $resolver->baseUrl());
    }
}
