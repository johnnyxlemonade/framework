<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\MiddlewarePipeline;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\RouteMatch;
use Lemonade\Framework\Routing\RouteRequestAttributes;
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
        DispatchRouteContextMiddleware::$match = null;
        DispatchContextController::$match = null;
        DispatchContextController::$request = null;
        GlobalRouteContextMiddleware::$match = null;
    }

    public function testHandleResolvesRouteAndRunsRouteMiddlewareBeforeController(): void
    {
        $container = $this->buildContainer();
        $router = new Router();
        $router->get('/demo', \Lemonade\Framework\Routing\ControllerAction::for(DispatchTestController::class, 'index'))
            ->middleware(DispatchMiddlewareOne::class, DispatchMiddlewareTwo::class);

        $handler = $this->buildHandler($router, $container);
        $request = (new Psr17Factory())->createServerRequest('GET', '/demo');
        $response = $handler->handle($request);

        self::assertSame('m1-before,m2-before,controller,m2-after,m1-after', (string) $response->getBody());
    }

    public function testInvalidResolvedMiddlewareThrowsRuntimeExceptionWithClassName(): void
    {
        $container = $this->buildContainer();
        $container->set(DispatchMiddlewareOne::class, new \stdClass());

        $router = new Router();
        $router->get('/demo', \Lemonade\Framework\Routing\ControllerAction::for(DispatchTestController::class, 'index'))
            ->middleware(DispatchMiddlewareOne::class);

        $handler = $this->buildHandler($router, $container);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Target class "%s" is not a valid middleware.',
            DispatchMiddlewareOne::class,
        ));
        $handler->handle((new Psr17Factory())->createServerRequest('GET', '/demo'));
    }

    public function testBenchmarkWithNullCurrentDoesNotCrash(): void
    {
        $container = $this->buildContainer();
        $router = new Router();
        $router->get('/demo', \Lemonade\Framework\Routing\ControllerAction::for(DispatchTestController::class, 'index'));

        $handler = $this->buildHandler($router, $container);
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
        $router->get('/demo', \Lemonade\Framework\Routing\ControllerAction::for(DispatchTestController::class, 'index'));

        $handler = $this->buildHandler($router, $container);
        $handler->handle((new Psr17Factory())->createServerRequest('GET', '/demo'));

        $names = array_map(
            static fn(array $mark): string => $mark['name'],
            $run->marks(),
        );

        self::assertContains('route_match_start', $names);
        self::assertContains('route_matched', $names);
    }

    public function testMatchedRouteContextIsSharedByRouteMiddlewareAndController(): void
    {
        $container = $this->buildContainer();
        $router = new Router();
        $router->getNamed('dispatch.context', '/context/{id}', \Lemonade\Framework\Routing\ControllerAction::for(DispatchContextController::class, 'show'))
            ->middleware(DispatchRouteContextMiddleware::class);

        $response = MiddlewarePipeline::create(
            [new GlobalRouteContextMiddleware()],
            $this->buildHandler($router, $container),
        )->handle((new Psr17Factory())->createServerRequest('GET', '/context/42'));

        self::assertSame('42', (string) $response->getBody());
        self::assertNull(GlobalRouteContextMiddleware::$match);
        self::assertInstanceOf(RouteMatch::class, DispatchRouteContextMiddleware::$match);
        self::assertSame(DispatchRouteContextMiddleware::$match, DispatchContextController::$match);
        self::assertInstanceOf(RouteMatch::class, DispatchContextController::$match);
        self::assertSame('dispatch.context', DispatchContextController::$match->name());
        self::assertSame(['id' => '42'], DispatchContextController::$match->params());
    }

    public function testNonMatchedRequestDoesNotExposeRouteContextToGlobalMiddleware(): void
    {
        $pipeline = MiddlewarePipeline::create(
            [new GlobalRouteContextMiddleware()],
            $this->buildHandler(new Router(), $this->buildContainer()),
        );

        $this->expectException(\Lemonade\Framework\Routing\Exception\RouteNotFoundException::class);

        try {
            $pipeline->handle((new Psr17Factory())->createServerRequest('GET', '/missing'));
        } finally {
            self::assertNull(GlobalRouteContextMiddleware::$match);
        }
    }

    public function testMatchedRouteAddsBenchmarkMetadataFromRequestAttributeContract(): void
    {
        $container = $this->buildContainer();
        $benchmark = new Benchmark();
        $run = $benchmark->start();
        $container->singleton(Benchmark::class, $benchmark);

        $router = new Router();
        $router->getNamed('dispatch.context', '/context/{id}', \Lemonade\Framework\Routing\ControllerAction::for(DispatchContextController::class, 'show'));

        $this->buildHandler($router, $container)
            ->handle((new Psr17Factory())->createServerRequest('GET', '/context/42'));

        $context = $run->toArray()['context'];

        self::assertSame('dispatch.context', $context['route']);
        self::assertSame(DispatchContextController::class, $context['controller']);
        self::assertSame('show', $context['action']);
    }

    public function testMatchedRequestReplacesTheScopeLocalRequestBinding(): void
    {
        $container = $this->buildContainer();
        $scope = $container->beginScope(ScopeKind::Request);
        $originalRequest = (new Psr17Factory())->createServerRequest('GET', '/context/42');
        $scope->bindScopedInstance(ServerRequestInterface::class, $originalRequest);

        $router = new Router();
        $router->get('/context/{id}', \Lemonade\Framework\Routing\ControllerAction::for(DispatchContextController::class, 'show'));
        $benchmark = $container->get(Benchmark::class);
        $handler = new DispatchRequestHandler(
            router: $router,
            resolver: new ControllerResolver($scope, $benchmark),
            middlewareResolver: new MiddlewareResolver($scope),
            benchmark: $benchmark,
            scope: $scope,
        );

        $handler->handle($originalRequest);

        $matchedRequest = $scope->get(ServerRequestInterface::class);

        self::assertInstanceOf(ServerRequestInterface::class, $matchedRequest);
        self::assertNotSame($originalRequest, $matchedRequest);
        self::assertSame($matchedRequest, DispatchContextController::$request);
        self::assertInstanceOf(RouteMatch::class, $matchedRequest->getAttribute(RouteRequestAttributes::MATCH));
    }

    public function testExplicitRouteDispatchesPublicAction(): void
    {
        $router = new Router();
        $router->get('/visibility/show', \Lemonade\Framework\Routing\ControllerAction::for(DispatchVisibilityController::class, 'show'));

        $response = $this->buildHandler($router, $this->buildContainer())
            ->handle((new Psr17Factory())->createServerRequest('GET', '/visibility/show'));

        self::assertSame('public', (string) $response->getBody());
    }

    /**
     * @dataProvider nonPublicExplicitActionProvider
     */
    public function testExplicitRouteRejectsNonPublicActionBeforeInvocationWithDispatch(string $action): void
    {
        DispatchVisibilityController::$invoked = false;
        $router = new Router();
        $router->get('/visibility/' . $action, \Lemonade\Framework\Routing\ControllerAction::for(DispatchVisibilityController::class, $action));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be public');

        try {
            $this->buildHandler($router, $this->buildContainer())
                ->handle((new Psr17Factory())->createServerRequest('GET', '/visibility/' . $action));
        } finally {
            self::assertFalse(DispatchVisibilityController::$invoked);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function nonPublicExplicitActionProvider(): iterable
    {
        yield 'protected action' => ['hidden'];
        yield 'private action' => ['secret'];
    }

    public function testUnregisteredConventionPathDoesNotDispatch(): void
    {
        $router = new Router();

        $this->expectException(\Lemonade\Framework\Routing\Exception\RouteNotFoundException::class);
        $this->buildHandler($router, $this->buildContainer())
            ->handle((new Psr17Factory())->createServerRequest('GET', '/dispatch-visibility/show'));
    }

    /**
     * @dataProvider nonPublicConventionActionProvider
     */
    public function testExplicitRouteRejectsNonPublicActionBeforeInvocation(string $action): void
    {
        DispatchVisibilityController::$invoked = false;
        $router = new Router();
        $router->get('/dispatch-visibility/' . $action, \Lemonade\Framework\Routing\ControllerAction::for(DispatchVisibilityController::class, $action));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be public');

        try {
            $this->buildHandler($router, $this->buildContainer())
                ->handle((new Psr17Factory())->createServerRequest('GET', '/dispatch-visibility/' . $action));
        } finally {
            self::assertFalse(DispatchVisibilityController::$invoked);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function nonPublicConventionActionProvider(): iterable
    {
        yield 'protected action' => ['hidden'];
        yield 'private action' => ['secret'];
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
        $container->singleton(DispatchContextController::class, DispatchContextController::class);
        $container->singleton(Benchmark::class, new Benchmark());

        return $container;
    }

    private function buildHandler(Router $router, Container $container): DispatchRequestHandler
    {
        return new DispatchRequestHandler(
            router: $router,
            resolver: new ControllerResolver($container, $container->get(Benchmark::class)),
            middlewareResolver: new MiddlewareResolver($container),
            benchmark: $container->get(Benchmark::class),
        );
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

final class DispatchTestController
{
    public function index(): ResponseInterface
    {
        DispatchFlowRecorder::$events[] = 'controller';
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream('controller'));
    }
}

final class DispatchContextController
{
    public static ?RouteMatch $match = null;
    public static ?ServerRequestInterface $request = null;

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        self::$request = $request;
        $match = $request->getAttribute(RouteRequestAttributes::MATCH);
        self::$match = $match instanceof RouteMatch ? $match : null;

        return (new Psr17Factory())
            ->createResponse()
            ->withBody((new Psr17Factory())->createStream((string) $id));
    }
}

final class DispatchRouteContextMiddleware implements MiddlewareInterface
{
    public static ?RouteMatch $match = null;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $match = $request->getAttribute(RouteRequestAttributes::MATCH);
        self::$match = $match instanceof RouteMatch ? $match : null;

        return $handler->handle($request);
    }
}

final class GlobalRouteContextMiddleware implements MiddlewareInterface
{
    public static ?RouteMatch $match = null;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $match = $request->getAttribute(RouteRequestAttributes::MATCH);
        self::$match = $match instanceof RouteMatch ? $match : null;

        return $handler->handle($request);
    }
}

final class DispatchVisibilityController
{
    public static bool $invoked = false;

    public function show(): ResponseInterface
    {
        self::$invoked = true;

        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream('public'));
    }

    protected function hidden(): ResponseInterface
    {
        self::$invoked = true;

        return (new Psr17Factory())->createResponse(200);
    }

    // @phpstan-ignore-next-line
    private function secret(): ResponseInterface
    {
        self::$invoked = true;

        return (new Psr17Factory())->createResponse(200);
    }
}

final class DispatchFlowRecorder
{
    /** @var list<string> */
    public static array $events = [];
}
