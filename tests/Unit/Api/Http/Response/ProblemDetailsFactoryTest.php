<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Http\Response;

use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsFactoryTest extends TestCase
{
    public function testNotFoundReturnsProblemJson404(): void
    {
        $factory = new ProblemDetailsFactory(new Psr17Factory());
        $request = (new Psr17Factory())->createServerRequest('GET', '/api/missing');

        $response = $factory->notFound($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('Not Found', $decoded['title'] ?? null);
        self::assertSame(404, $decoded['status'] ?? null);
        self::assertSame('/api/missing', $decoded['instance'] ?? null);
    }
}
