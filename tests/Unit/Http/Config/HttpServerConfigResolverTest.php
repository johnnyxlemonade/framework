<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Config;

use InvalidArgumentException;
use Lemonade\Framework\Http\Config\ForwardedHeaderProfile;
use Lemonade\Framework\Http\Config\HttpServerConfigDefinition;
use Lemonade\Framework\Http\Config\HttpServerConfigResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpServerConfigResolverTest extends TestCase
{
    public function testDefaultsDenyAllProxyAndHostTrust(): void
    {
        $config = (new HttpServerConfigResolver())->resolve();

        self::assertSame([], $config->trustedProxies());
        self::assertSame(ForwardedHeaderProfile::None, $config->forwardedHeaderProfile());
        self::assertFalse($config->trustsForwardedPrefix());
        self::assertSame([], $config->allowedHosts());
        self::assertSame([], get_object_vars($config));
    }

    public function testLaterDefinitionReplacesEachTrustSetting(): void
    {
        $config = (new HttpServerConfigResolver())->resolve(
            HttpServerConfigDefinition::create()
                ->trustedProxies(['127.0.0.1'])
                ->forwardedHeaderProfile(ForwardedHeaderProfile::Forwarded)
                ->allowedHosts(['first.example']),
            HttpServerConfigDefinition::create()
                ->trustedProxies(['10.0.0.0/8', '2001:db8::/32'])
                ->forwardedHeaderProfile('x_forwarded')
                ->trustForwardedPrefix()
                ->allowedHosts(['example.com', '*.example.com']),
        );

        self::assertSame(['10.0.0.0/8', '2001:db8::/32'], $config->trustedProxies());
        self::assertSame(ForwardedHeaderProfile::XForwarded, $config->forwardedHeaderProfile());
        self::assertTrue($config->trustsForwardedPrefix());
        self::assertSame(['example.com', '*.example.com'], $config->allowedHosts());
    }

    #[DataProvider('invalidDefinitions')]
    public function testRejectsInvalidTrustConfiguration(HttpServerConfigDefinition $definition): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new HttpServerConfigResolver())->resolve($definition);
    }

    /**
     * @return iterable<string, array{HttpServerConfigDefinition}>
     */
    public static function invalidDefinitions(): iterable
    {
        yield 'unknown profile' => [HttpServerConfigDefinition::create()->forwardedHeaderProfile('both')];
        yield 'non-canonical profile' => [HttpServerConfigDefinition::create()->forwardedHeaderProfile('X_FORWARDED')];
        yield 'hostname proxy' => [HttpServerConfigDefinition::create()->trustedProxies(['proxy.example'])];
        yield 'proxy whitespace' => [HttpServerConfigDefinition::create()->trustedProxies([' 10.0.0.1'])];
        yield 'invalid IPv4 CIDR' => [HttpServerConfigDefinition::create()->trustedProxies(['10.0.0.0/33'])];
        yield 'invalid IPv6 CIDR' => [HttpServerConfigDefinition::create()->trustedProxies(['2001:db8::/129'])];
        yield 'middle wildcard' => [HttpServerConfigDefinition::create()->allowedHosts(['api.*.example.com'])];
        yield 'wildcard IP' => [HttpServerConfigDefinition::create()->allowedHosts(['*.127.0.0.1'])];
        yield 'host with port' => [HttpServerConfigDefinition::create()->allowedHosts(['example.com:443'])];
        yield 'host with path' => [HttpServerConfigDefinition::create()->allowedHosts(['example.com/path'])];
        yield 'host whitespace' => [HttpServerConfigDefinition::create()->allowedHosts(['example.com '])];
    }
}
