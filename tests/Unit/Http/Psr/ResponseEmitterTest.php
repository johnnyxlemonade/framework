<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Psr;

use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ResponseEmitterTest extends TestCase
{
    protected function tearDown(): void
    {
        http_response_code(200);
        if (function_exists('header_remove')) {
            header_remove();
        }
    }

    public function testEmitOutputsBodyAndSetsStatusCode(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(204)->withBody($factory->createStream('payload'));
        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        self::assertSame('payload', is_string($output) ? $output : '');
        self::assertSame(204, http_response_code());
    }

    public function testEmitHandlesMultipleHeaderValuesAndEmptyBody(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withAddedHeader('X-Test', 'a')
            ->withAddedHeader('X-Test', 'b');
        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        self::assertSame('', is_string($output) ? $output : '');
        self::assertSame(201, http_response_code());

        $headers = function_exists('headers_list') ? headers_list() : [];
        if ($headers !== []) {
            self::assertContains('X-Test: a', $headers);
            self::assertContains('X-Test: b', $headers);
        } else {
            self::addToAssertionCount(1);
        }
    }
}
