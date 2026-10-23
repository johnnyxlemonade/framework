<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use InvalidArgumentException;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Http\Request\Exception\InvalidRequestMetadataException;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Support\BaseUrlResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BaseUrlResolverTest extends TestCase
{
    public function testConfiguredHttpsBaseUrlIsUsedAndTrailingSlashTrimmed(): void
    {
        $resolver = new BaseUrlResolver($this->appConfig('https://example.com/'));

        self::assertSame('https://example.com', $resolver->baseUrl());
    }

    public function testConfiguredBaseUrlMustBeAnExplicitAbsoluteUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('App base_url must be an absolute http:// or https:// URL');

        $this->appConfig('example.com');
    }

    public function testBaseUrlJoinsPathCorrectly(): void
    {
        $resolver = new BaseUrlResolver($this->appConfig('https://example.com/root'));

        self::assertSame('https://example.com/root/path/to/page', $resolver->baseUrl('path/to/page'));
    }

    public function testValidatedRequestOriginIncludesSchemePortAndPrefix(): void
    {
        $resolver = new BaseUrlResolver(
            $this->appConfig(),
            $this->context('https', 'api.example.com', 8443, '/gateway'),
        );

        self::assertSame('https://api.example.com:8443/gateway', $resolver->baseUrl());
    }

    public function testConfiguredPathIsNotCombinedWithForwardedPrefix(): void
    {
        $resolver = new BaseUrlResolver(
            $this->appConfig('https://canonical.example/application'),
            $this->context('https', 'proxy.example', null, '/gateway'),
        );

        self::assertSame('https://canonical.example/application', $resolver->baseUrl());
    }

    public function testIpv6RequestHostIsRenderedAsAnAuthorityLiteral(): void
    {
        $resolver = new BaseUrlResolver(
            $this->appConfig(),
            $this->context('http', '2001:db8::10', 8080, ''),
        );

        self::assertSame('http://[2001:db8::10]:8080', $resolver->baseUrl());
    }

    public function testRequestWithoutAllowlistedHostFailsClosed(): void
    {
        $resolver = new BaseUrlResolver(
            $this->appConfig(),
            $this->context('https', null, null, ''),
        );

        $this->expectException(InvalidRequestMetadataException::class);
        $this->expectExceptionMessage('Configure app.base_url or add the exact host');

        $resolver->baseUrl();
    }

    public function testRequestDerivedUrlOutsideHttpScopeFailsWithConfigurationGuidance(): void
    {
        $resolver = new BaseUrlResolver($this->appConfig());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configure app.base_url explicitly');

        $resolver->baseUrl();
    }

    private function appConfig(?string $baseUrl = null): AppConfig
    {
        return new AppConfig(
            timezone: null,
            baseUrl: $baseUrl,
            basePath: '',
            publicPath: '',
            env: 'testing',
            debug: false,
            appPath: '',
            configPath: '',
            storagePath: '',
        );
    }

    /**
     * @param 'http'|'https' $scheme
     */
    private function context(
        string $scheme,
        ?string $host,
        ?int $port,
        string $prefix,
    ): ResolvedRequestContext {
        return new ResolvedRequestContext(
            peerIp: '10.0.0.5',
            clientIp: '198.51.100.25',
            viaTrustedProxy: true,
            scheme: $scheme,
            host: $host,
            port: $port,
            prefix: $prefix,
        );
    }
}
