<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Request;

use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Http\Request\ServerHelper;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ServerHelperTest extends TestCase
{
    public function testRawValuesComeOnlyFromTheExplicitRequest(): void
    {
        $request = new ServerRequest(
            method: 'GET',
            uri: 'http://example.test',
            serverParams: ['HTTPS' => 'off', 'REQUEST_ID' => 'request-value'],
        );
        $helper = new ServerHelper($request, $this->context('http', 'example.test'));

        self::assertSame('off', $helper->get('HTTPS'));
        self::assertSame('request-value', $helper->get('REQUEST_ID'));
        self::assertTrue($helper->has('REQUEST_ID'));
        self::assertSame($request->getServerParams(), $helper->all());
    }

    public function testSchemeAndHttpsDelegateToTheResolvedContext(): void
    {
        $request = (new ServerRequest('GET', 'http://backend.test'))
            ->withHeader('X-Forwarded-Proto', 'http');
        $helper = new ServerHelper($request, $this->context('https', 'public.example'));

        self::assertSame('https', $helper->scheme());
        self::assertTrue($helper->isHttps());
    }

    public function testHostAndClientAddressUseValidatedContextValues(): void
    {
        $request = new ServerRequest('GET', 'http://evil.example');
        $context = new ResolvedRequestContext(
            peerIp: '10.0.0.5',
            clientIp: '198.51.100.25',
            viaTrustedProxy: true,
            scheme: 'https',
            host: 'api.example.com',
            port: null,
            prefix: '',
        );
        $helper = new ServerHelper($request, $context);

        self::assertSame('api.example.com', $helper->host());
        self::assertSame('198.51.100.25', $helper->clientIp());
    }

    public function testMissingTrustedHostUsesOnlyTheExplicitDisplayFallback(): void
    {
        $helper = new ServerHelper(
            new ServerRequest('GET', 'http://evil.example'),
            $this->context('http', null),
        );

        self::assertSame('fallback.example', $helper->host('fallback.example'));
    }

    /**
     * @param 'http'|'https' $scheme
     */
    private function context(string $scheme, ?string $host): ResolvedRequestContext
    {
        return new ResolvedRequestContext(
            peerIp: '203.0.113.10',
            clientIp: '203.0.113.10',
            viaTrustedProxy: false,
            scheme: $scheme,
            host: $host,
            port: null,
            prefix: '',
        );
    }
}
