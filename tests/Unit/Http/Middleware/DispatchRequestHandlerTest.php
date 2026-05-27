<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Controller;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DispatchRequestHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        DispatchFlowRecorder::$events = [];
    }

    public function testHandleResolvesRouteAndRunsRouteMiddlewareBeforeController(): void
    {
        $container = $this->buildContainer();
        $router = new Router();
        $router->get('/demo', DispatchTestController::class . '@index')
            ->middleware(DispatchMiddlewareOne::class, DispatchMiddlewareTwo::class);

        $handler = new DispatchRequestHandler($router, new ControllerResolver($container), $container);
        $request = (new Psr17Factory())->createServerRequest('GET', '/demo');
        $response = $handler->handle($request);

        self::assertSame('m1-before,m2-before,controller,m2-after,m1-after', (string) $response->getBody());
    }

    public function testInvalidResolvedMiddlewareThrowsRuntimeExceptionWithClassName(): void
    {
        $container = $this->buildContainer();
        $container->set(DispatchMiddlewareOne::class, new \stdClass());

        $router = new Router();
        $router->get('/demo', DispatchTestController::class . '@index')
            ->middleware(DispatchMiddlewareOne::class);

        $handler = new DispatchRequestHandler($router, new ControllerResolver($container), $container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(DispatchMiddlewareOne::class);
        $handler->handle((new Psr17Factory())->createServerRequest('GET', '/demo'));
    }

    public function testNoBenchmarkBoundDoesNotCrash(): void
    {
        $container = $this->buildContainer();
        $router = new Router();
        $router->get('/demo', DispatchTestController::class . '@index');

        $handler = new DispatchRequestHandler($router, new ControllerResolver($container), $container);
        $response = $handler->handle((new Psr17Factory())->createServerRequest('GET', '/demo'));

        self::assertSame('controller', (string) $response->getBody());
    }

    public function testBenchmarkBoundWithNullCurrentDoesNotCrash(): void
    {
        $container = $this->buildContainer();
        $container->singleton(Benchmark::class, new Benchmark());
        $router = new Router();
        $router->get('/demo', DispatchTestController::class . '@index');

        $handler = new DispatchRequestHandler($router, new ControllerResolver($container), $container);
        $response = $handler->handle((new Psr17Factory())->createServerRequest('GET', '/demo'));

        self::assertSame('controller', (string) $response->getBody());
    }

    public function testBenchmarkMarksRouteMatchStartAndRouteMatchedWhenRunExists(): void
    {
        $container = $this->buildContainer();
        $benchmark = new Benchmark();
        $run = $benchmark->start();
        $container->singleton(Benchmark::class, $benchmark);

        $router = new Router();
        $router->get('/demo', DispatchTestController::class . '@index');

        $handler = new DispatchRequestHandler($router, new ControllerResolver($container), $container);
        $handler->handle((new Psr17Factory())->createServerRequest('GET', '/demo'));

        $names = array_map(
            static fn(array $mark): string => $mark['name'],
            $run->marks(),
        );

        self::assertContains('route_match_start', $names);
        self::assertContains('route_matched', $names);
    }

    private function buildContainer(): Container
    {
        $container = new Container();
        $factory = new Psr17Factory();

        $container->singleton(\Psr\Http\Message\ResponseFactoryInterface::class, $factory);
        $container->singleton(\Psr\Http\Message\StreamFactoryInterface::class, $factory);
        $container->singleton(DispatchMiddlewareOne::class, DispatchMiddlewareOne::class);
        $container->singleton(DispatchMiddlewareTwo::class, DispatchMiddlewareTwo::class);
        $container->singleton(DispatchTestController::class, DispatchTestController::class);

        return $container;
    }
}

final class DispatchMiddlewareOne implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        DispatchFlowRecorder::$events[] = 'm1-before';
        $response = $handler->handle($request);
        DispatchFlowRecorder::$events[] = 'm1-after';

        $factory = new Psr17Factory();
        $body = $factory->createStream(implode(',', DispatchFlowRecorder::$events));

        return $response->withBody($body);
    }
}

final class DispatchMiddlewareTwo implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        DispatchFlowRecorder::$events[] = 'm2-before';
        $response = $handler->handle($request);
        DispatchFlowRecorder::$events[] = 'm2-after';

        return $response;
    }
}

final class DispatchTestController extends Controller
{
    public function index(): ResponseInterface
    {
        DispatchFlowRecorder::$events[] = 'controller';
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream('controller'));
    }
}

final class DispatchFlowRecorder
{
    /** @var list<string> */
    public static array $events = [];
}
