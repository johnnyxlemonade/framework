<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Request;

use Lemonade\Framework\Http\Config\ForwardedHeaderProfile;
use Lemonade\Framework\Http\Config\HttpServerConfig;
use Lemonade\Framework\Http\Request\Exception\InvalidRequestMetadataException;
use Lemonade\Framework\Http\Request\RequestContextResolver;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Http\Request\TrustedProxyMatcher;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RequestContextResolverTest extends TestCase
{
    public function testDefaultPolicyIgnoresSpoofedForwardingMetadata(): void
    {
        $request = $this->request('http://direct.example/path', '203.0.113.20')
            ->withHeader('Forwarded', 'for=198.51.100.1;proto=https;host=evil.example')
            ->withHeader('X-Forwarded-For', '198.51.100.2')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'evil.example');

        $context = $this->resolve(
            request: $request,
            allowedHosts: ['direct.example'],
        );

        self::assertSame('203.0.113.20', $context->peerIp());
        self::assertSame('203.0.113.20', $context->clientIp());
        self::assertFalse($context->viaTrustedProxy());
        self::assertSame('http', $context->scheme());
        self::assertFalse($context->secure());
        self::assertSame('direct.example', $context->host());
    }

    public function testNoneProfileIgnoresForwardingMetadataFromATrustedPeer(): void
    {
        $request = $this->request('http://backend.local/path', '10.0.0.5')
            ->withHeader('X-Forwarded-For', '198.51.100.25')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'evil.example');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            allowedHosts: ['backend.local'],
        );

        self::assertTrue($context->viaTrustedProxy());
        self::assertSame('10.0.0.5', $context->clientIp());
        self::assertSame('http', $context->scheme());
        self::assertSame('backend.local', $context->host());
    }

    public function testTrustedXForwardedProxyResolvesSingleHopOriginAndPrefix(): void
    {
        $request = $this->request('http://backend.local/path', '10.0.0.5')
            ->withHeader('X-Forwarded-For', '198.51.100.25')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'api.example.com')
            ->withHeader('X-Forwarded-Port', '443')
            ->withHeader('X-Forwarded-Prefix', '/gateway/');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            trustPrefix: true,
            allowedHosts: ['api.example.com'],
        );

        self::assertSame('198.51.100.25', $context->clientIp());
        self::assertTrue($context->viaTrustedProxy());
        self::assertSame('https', $context->scheme());
        self::assertTrue($context->secure());
        self::assertSame('api.example.com', $context->host());
        self::assertNull($context->port());
        self::assertSame('/gateway', $context->prefix());
    }

    public function testForwardedOriginDoesNotInheritTheBackendPort(): void
    {
        $request = $this->request('http://backend.local:8080/path', '10.0.0.5')
            ->withHeader('X-Forwarded-For', '198.51.100.25')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'api.example.com');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            allowedHosts: ['api.example.com'],
        );

        self::assertSame('https', $context->scheme());
        self::assertSame('api.example.com', $context->host());
        self::assertNull($context->port());
    }

    public function testForwardedClientAddressAlonePreservesTheDirectOrigin(): void
    {
        $request = $this->request('http://backend.local:8080/path', '10.0.0.5')
            ->withHeader('X-Forwarded-For', '198.51.100.25');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            allowedHosts: ['backend.local'],
        );

        self::assertSame('198.51.100.25', $context->clientIp());
        self::assertSame('http', $context->scheme());
        self::assertSame('backend.local', $context->host());
        self::assertSame(8080, $context->port());
    }

    public function testProxyChainStopsAtFirstUntrustedHopFromTheRight(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.3')
            ->withHeader('X-Forwarded-For', '192.0.2.44, 198.51.100.8')
            ->withHeader('X-Forwarded-Proto', 'http, https')
            ->withHeader('X-Forwarded-Host', 'evil.example, api.example.com')
            ->withHeader('X-Forwarded-Port', '80, 8443');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            allowedHosts: ['api.example.com'],
        );

        self::assertSame('198.51.100.8', $context->clientIp());
        self::assertSame('https', $context->scheme());
        self::assertSame('api.example.com', $context->host());
        self::assertSame(8443, $context->port());
    }

    public function testTrustedProxyMayReplaceOriginMetadataWithOneAuthoritativeValue(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.3')
            ->withHeader('X-Forwarded-For', '192.0.2.44, 198.51.100.8')
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-Host', 'api.example.com')
            ->withHeader('X-Forwarded-Port', '443');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            allowedHosts: ['api.example.com'],
        );

        self::assertSame('198.51.100.8', $context->clientIp());
        self::assertSame('https', $context->scheme());
        self::assertSame('api.example.com', $context->host());
        self::assertNull($context->port());
    }

    public function testPrivateClientAddressIsNotReplacedByPublicCandidate(): void
    {
        $request = $this->request('http://backend.local', '192.0.2.5')
            ->withHeader('X-Forwarded-For', '10.20.30.40');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['192.0.2.5'],
            profile: ForwardedHeaderProfile::XForwarded,
        );

        self::assertSame('10.20.30.40', $context->clientIp());
    }

    public function testForwardedProfileAcceptsQuotedIpv6AndAlignedOrigin(): void
    {
        $request = $this->request('http://backend.local', '2001:db8:ffff::2')
            ->withHeader(
                'Forwarded',
                'for="[2001:db8:1::25]:4321";proto=https;host="[2001:db8:2::10]:8443"',
            );

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['2001:db8:ffff::/48'],
            profile: ForwardedHeaderProfile::Forwarded,
            allowedHosts: ['2001:db8:2::10'],
        );

        self::assertSame('2001:db8:1::25', $context->clientIp());
        self::assertSame('https', $context->scheme());
        self::assertSame('2001:db8:2::10', $context->host());
        self::assertSame(8443, $context->port());
    }

    public function testEmptyAllowedHostsDoesNotApproveRequestHost(): void
    {
        $context = $this->resolve(
            request: $this->request('https://example.com', '203.0.113.4'),
        );

        self::assertSame('https', $context->scheme());
        self::assertNull($context->host());
        self::assertNull($context->port());
    }

    public function testSuffixWildcardMatchesOnlySubdomainsAtLabelBoundary(): void
    {
        $subdomain = $this->resolve(
            request: $this->request('https://api.example.com', '203.0.113.4'),
            allowedHosts: ['*.example.com'],
        );
        $apex = $this->resolve(
            request: $this->request('https://example.com', '203.0.113.4'),
            allowedHosts: ['*.example.com'],
        );
        $lookalike = $this->resolve(
            request: $this->request('https://evil-example.com', '203.0.113.4'),
            allowedHosts: ['*.example.com'],
        );

        self::assertSame('api.example.com', $subdomain->host());
        self::assertNull($apex->host());
        self::assertNull($lookalike->host());
    }

    public function testDirectServerHttpsMetadataIsResolvedWithoutForwardingHeaders(): void
    {
        $request = new ServerRequest(
            method: 'GET',
            uri: '/',
            headers: ['Host' => 'secure.example'],
            serverParams: [
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTPS' => 'on',
                'SERVER_PORT' => '443',
            ],
        );

        $context = $this->resolve($request, allowedHosts: ['secure.example']);

        self::assertTrue($context->secure());
        self::assertSame('secure.example', $context->host());
        self::assertNull($context->port());
    }

    public function testIdenticalRepeatedHostValuesFromPsrRequestCreationAreAccepted(): void
    {
        $request = $this->request('https://example.com/path', '203.0.113.10')
            ->withAddedHeader('Host', 'example.com');

        $context = $this->resolve(
            request: $request,
            allowedHosts: ['example.com'],
        );

        self::assertSame('example.com', $context->host());
        self::assertSame('https', $context->scheme());
    }

    public function testConflictingRepeatedHostValuesAreRejected(): void
    {
        $request = $this->request('https://example.com/path', '203.0.113.10')
            ->withAddedHeader('Host', 'evil.example');

        $this->expectException(InvalidRequestMetadataException::class);
        $this->expectExceptionMessage('Repeated Host header values must describe the same authority.');

        $this->resolve(
            request: $request,
            allowedHosts: ['example.com'],
        );
    }

    public function testUntrustedMalformedForwardedChainIsIgnored(): void
    {
        $request = $this->request('http://direct.example', '203.0.113.8')
            ->withHeader('X-Forwarded-For', 'not-an-ip, unknown')
            ->withHeader('X-Forwarded-Proto', 'javascript');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            allowedHosts: ['direct.example'],
        );

        self::assertSame('203.0.113.8', $context->clientIp());
        self::assertSame('http', $context->scheme());
    }

    public function testRejectsCombinedForwardedHeaderFamiliesFromTrustedProxy(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('Forwarded', 'for=198.51.100.2')
            ->withHeader('X-Forwarded-For', '198.51.100.2');

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
        );
    }

    public function testRejectsMisalignedXForwardedOriginMetadata(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.3')
            ->withHeader('X-Forwarded-For', '198.51.100.1, 10.0.0.2, 10.0.0.4')
            ->withHeader('X-Forwarded-Proto', 'https, http');

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
        );
    }

    public function testRejectsObfuscatedForwardedNode(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('Forwarded', 'for=_hidden;proto=https');

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::Forwarded,
        );
    }

    public function testRejectsTooManyProxyHops(): void
    {
        $chain = implode(', ', array_fill(0, 33, '198.51.100.1'));
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('X-Forwarded-For', $chain);

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
        );
    }

    public function testRejectsConflictingForwardedPorts(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('X-Forwarded-For', '198.51.100.4')
            ->withHeader('X-Forwarded-Host', 'api.example.com:8443')
            ->withHeader('X-Forwarded-Port', '443');

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            allowedHosts: ['api.example.com'],
        );
    }

    public function testRejectsMalformedForwardedPort(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('X-Forwarded-For', '198.51.100.4')
            ->withHeader('X-Forwarded-Port', '70000');

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
        );
    }

    public function testRejectsUnsafeForwardedPrefix(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('X-Forwarded-For', '198.51.100.4')
            ->withHeader('X-Forwarded-Prefix', '/gateway/../admin');

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            trustPrefix: true,
        );
    }

    public function testRejectsAmbiguousDoubleSlashForwardedPrefix(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('X-Forwarded-For', '198.51.100.4')
            ->withHeader('X-Forwarded-Prefix', '/gateway//admin');

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
            trustPrefix: true,
        );
    }

    public function testDisabledPrefixTrustIgnoresMalformedPrefix(): void
    {
        $request = $this->request('http://backend.local', '10.0.0.2')
            ->withHeader('X-Forwarded-For', '198.51.100.4')
            ->withHeader('X-Forwarded-Prefix', '/../unsafe');

        $context = $this->resolve(
            request: $request,
            trustedProxies: ['10.0.0.0/8'],
            profile: ForwardedHeaderProfile::XForwarded,
        );

        self::assertSame('', $context->prefix());
    }

    public function testRejectsMalformedRemoteAddress(): void
    {
        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve($this->request('http://example.com', 'not-an-ip'));
    }

    public function testRejectsRemoteAddressWithSurroundingWhitespace(): void
    {
        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve($this->request('http://example.com', ' 203.0.113.8 '));
    }

    public function testRejectsMalformedDirectHost(): void
    {
        $request = new ServerRequest(
            method: 'GET',
            uri: '/',
            headers: ['Host' => 'bad host.example'],
            serverParams: ['REMOTE_ADDR' => '203.0.113.8'],
        );

        $this->expectException(InvalidRequestMetadataException::class);

        $this->resolve($request, allowedHosts: ['example.com']);
    }

    /**
     * @param list<non-empty-string> $trustedProxies
     * @param list<non-empty-string> $allowedHosts
     */
    private function resolve(
        ServerRequestInterface $request,
        array $trustedProxies = [],
        ForwardedHeaderProfile $profile = ForwardedHeaderProfile::None,
        bool $trustPrefix = false,
        array $allowedHosts = [],
    ): ResolvedRequestContext {
        $config = new HttpServerConfig(
            trustedProxies: $trustedProxies,
            forwardedHeaderProfile: $profile,
            trustForwardedPrefix: $trustPrefix,
            allowedHosts: $allowedHosts,
        );

        return (new RequestContextResolver(
            $config,
            new TrustedProxyMatcher($trustedProxies),
        ))->resolve($request);
    }

    private function request(string $uri, string $remoteAddress): ServerRequestInterface
    {
        return new ServerRequest(
            method: 'GET',
            uri: $uri,
            serverParams: ['REMOTE_ADDR' => $remoteAddress],
        );
    }
}
