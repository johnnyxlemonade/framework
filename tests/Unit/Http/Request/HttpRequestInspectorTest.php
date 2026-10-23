<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Request;

use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HttpRequestInspectorTest extends TestCase
{
    public function testClientAndProxyMetadataComeOnlyFromResolvedContext(): void
    {
        $request = (new ServerRequest('GET', 'http://evil.example'))
            ->withHeader('Forwarded', 'for=203.0.113.10')
            ->withHeader('X-Forwarded-For', '198.51.100.20')
            ->withHeader('Client-IP', '192.0.2.30');
        $context = new ResolvedRequestContext(
            peerIp: '10.0.0.5',
            clientIp: '10.20.30.40',
            viaTrustedProxy: true,
            scheme: 'https',
            host: 'api.example.com',
            port: null,
            prefix: '',
        );
        $inspector = new HttpRequestInspector($context);

        self::assertSame('10.20.30.40', $inspector->clientIp($request));
        self::assertTrue($inspector->viaProxy($request));
        self::assertSame('10.20.30.40', $inspector->forwardedFor($request));
        self::assertSame('api.example.com', $inspector->host($request));
    }

    public function testInspectorWithoutRequestContextFailsClosedForTrustedMetadata(): void
    {
        $request = (new ServerRequest('GET', 'http://evil.example'))
            ->withHeader('X-Forwarded-For', '198.51.100.20');
        $inspector = new HttpRequestInspector();

        self::assertNull($inspector->clientIp($request));
        self::assertFalse($inspector->viaProxy($request));
        self::assertSame('', $inspector->forwardedFor($request));
        self::assertSame('UNKNOWN', $inspector->host($request));
    }
}
