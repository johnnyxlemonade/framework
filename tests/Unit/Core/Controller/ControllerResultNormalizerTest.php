<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Controller;

use Lemonade\Framework\Core\Controller\ControllerResultNormalizer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ControllerResultNormalizerTest extends TestCase
{
    public function testKeepsPsrResponseUnchangedWithoutResolvingFactories(): void
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(202);
        $factoryResolutions = 0;

        $normalized = (new ControllerResultNormalizer())->normalize(
            $response,
            static function () use (&$factoryResolutions): never {
                $factoryResolutions++;
                throw new \LogicException('Factory must not be resolved.');
            },
            static function (): never {
                throw new \LogicException('Factory must not be resolved.');
            },
        );

        self::assertSame($response, $normalized);
        self::assertSame(0, $factoryResolutions);
    }

    /**
     * @dataProvider scalarResultProvider
     */
    public function testNormalizesStringAndScalarResults(mixed $result, string $expectedBody): void
    {
        $factory = new Psr17Factory();

        $response = (new ControllerResultNormalizer())->normalize(
            $result,
            static fn(): Psr17Factory => $factory,
            static fn(): Psr17Factory => $factory,
        );

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
}
