<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Routing;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Controller;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Http\Psr\Psr17Factory;
use Lemonade\Framework\Routing\RouteMatch;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final class ControllerResolverTest extends TestCase
{
    public function testStringIntParamIsCastedToInt(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(CastingController::class, 'intAction', ['id' => '123']),
            $this->request(),
        );

        self::assertSame('123', (string) $response->getBody());
    }

    public function testInvalidIntStringThrowsRuntimeException(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $resolver->handle(
            new RouteMatch(CastingController::class, 'intAction', ['id' => 'abc']),
            $this->request(),
        );
    }

    public function testStringFloatParamIsCastedToFloat(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(CastingController::class, 'floatAction', ['value' => '12.5']),
            $this->request(),
        );

        self::assertSame('12.5', (string) $response->getBody());
    }

    public function testInvalidFloatStringThrowsRuntimeException(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $resolver->handle(
            new RouteMatch(CastingController::class, 'floatAction', ['value' => 'abc']),
            $this->request(),
        );
    }

    public function testBoolTrueStringCastsToTrue(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(CastingController::class, 'boolAction', ['flag' => 'true']),
            $this->request(),
        );

        self::assertSame('true', (string) $response->getBody());
    }

    public function testBoolFalseStringCastsToFalse(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(CastingController::class, 'boolAction', ['flag' => 'false']),
            $this->request(),
        );

        self::assertSame('false', (string) $response->getBody());
    }

    public function testBoolOneStringCastsToTrue(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(CastingController::class, 'boolAction', ['flag' => '1']),
            $this->request(),
        );

        self::assertSame('true', (string) $response->getBody());
    }

    public function testBoolZeroStringCastsToFalse(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(CastingController::class, 'boolAction', ['flag' => '0']),
            $this->request(),
        );

        self::assertSame('false', (string) $response->getBody());
    }

    public function testInvalidBoolStringThrowsRuntimeException(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $resolver->handle(
            new RouteMatch(CastingController::class, 'boolAction', ['flag' => 'yes']),
            $this->request(),
        );
    }

    public function testStringParamRemainsString(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(CastingController::class, 'stringAction', ['value' => 'hello']),
            $this->request(),
        );

        self::assertSame('hello', (string) $response->getBody());
    }

    private function resolver(): ControllerResolver
    {
        $container = new Container();
        $psr17 = new Psr17Factory();

        $container->singleton(ResponseFactoryInterface::class, $psr17);
        $container->singleton(StreamFactoryInterface::class, $psr17);

        return new ControllerResolver($container);
    }

    private function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/');
    }
}

final class CastingController extends Controller
{
    public function intAction(int $id): int
    {
        return $id;
    }

    public function floatAction(float $value): string
    {
        return (string) $value;
    }

    public function boolAction(bool $flag): string
    {
        return $flag ? 'true' : 'false';
    }

    public function stringAction(string $value): string
    {
        return $value;
    }
}
