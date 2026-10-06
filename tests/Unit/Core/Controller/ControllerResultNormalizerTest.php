<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Controller;

use Lemonade\Framework\Core\Controller\ControllerResultNormalizer;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Http\Response\Responses;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ControllerResultNormalizerTest extends TestCase
{
    public function testKeepsPsrResponseUnchanged(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(202);

        $normalized = $this->normalizer($factory)->normalize($response);

        self::assertSame($response, $normalized);
    }

    #[DataProvider('scalarResultProvider')]
    public function testNormalizesStringAndScalarResults(mixed $result, string $expectedBody): void
    {
        $factory = new Psr17Factory();

        $response = $this->normalizer($factory)->normalize($result);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame($expectedBody, (string) $response->getBody());
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function scalarResultProvider(): iterable
    {
        yield 'string' => ['content', 'content'];
        yield 'integer' => [42, '42'];
        yield 'boolean' => [true, '1'];
        yield 'null' => [null, ''];
        yield 'stringable' => [new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        }, 'stringable'];
    }

    private function normalizer(Psr17Factory $factory): ControllerResultNormalizer
    {
        return new ControllerResultNormalizer(
            new Responses(new ResponseBuilder($factory, $factory)),
        );
    }
}
