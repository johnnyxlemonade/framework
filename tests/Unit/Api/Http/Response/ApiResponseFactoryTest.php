<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Http\Response;

use JsonException;
use Lemonade\Framework\Api\Http\Response\ApiResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ApiResponseFactoryTest extends TestCase
{
    public function testJsonCreatesApiEnvelope(): void
    {
        $factory = new ApiResponseFactory(new Psr17Factory());

        $response = $factory->json(['ok' => true], 201, ['requestId' => 'abc']);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame(
            ['data' => ['ok' => true], 'meta' => ['requestId' => 'abc']],
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testJsonStillThrowsWhenPayloadCannotBeEncoded(): void
    {
        $factory = new ApiResponseFactory(new Psr17Factory());

        $this->expectException(JsonException::class);

        $factory->json(['invalid' => INF]);
    }
}
