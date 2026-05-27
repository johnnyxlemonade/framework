<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Controller;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Http\Middleware\ControllerRequestHandler;
use Lemonade\Framework\Routing\RouteMatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ControllerRequestHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        ControllerHandlerTestController::$called = 0;
    }

    public function testHandleDelegatesToControllerResolverAndReturnsItsResponse(): void
    {
        $container = new Container();
        $factory = new Psr17Factory();
        $container->singleton(\Psr\Http\Message\ResponseFactoryInterface::class, $factory);
        $container->singleton(\Psr\Http\Message\StreamFactoryInterface::class, $factory);
        $container->singleton(ControllerHandlerTestController::class, ControllerHandlerTestController::class);

        $resolver = new ControllerResolver($container);
        $match = new RouteMatch(ControllerHandlerTestController::class, 'show', ['id' => '42']);
        $handler = new ControllerRequestHandler($resolver, $match);
        $request = $factory->createServerRequest('GET', '/users/42');

        $response = $handler->handle($request);

        self::assertSame(1, ControllerHandlerTestController::$called);
        self::assertSame('user-42', (string) $response->getBody());
    }
}

final class ControllerHandlerTestController extends Controller
{
    public static int $called = 0;

    public function show(int $id): ResponseInterface
    {
        self::$called++;
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream('user-' . $id));
    }
}
