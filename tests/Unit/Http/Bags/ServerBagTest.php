<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Bags;

use Lemonade\Framework\Http\Bags\ServerBag;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Http\Request\ServerHelper;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ServerBagTest extends TestCase
{
    public function testTrustedMethodsDelegateToTheResolvedRequestContext(): void
    {
        $request = new ServerRequest(
            method: 'GET',
            uri: 'http://backend.local',
            serverParams: ['REMOTE_ADDR' => '10.0.0.5', 'REQUEST_ID' => 'request-id'],
        );
        $context = new ResolvedRequestContext(
            peerIp: '10.0.0.5',
            clientIp: '198.51.100.25',
            viaTrustedProxy: true,
            scheme: 'https',
            host: 'api.example.com',
            port: null,
            prefix: '',
        );
        $bag = new ServerBag(new ServerHelper($request, $context));

        self::assertSame('request-id', $bag->get('REQUEST_ID'));
        self::assertSame('api.example.com', $bag->host());
        self::assertSame('https', $bag->scheme());
        self::assertSame('198.51.100.25', $bag->ip());
    }
}
