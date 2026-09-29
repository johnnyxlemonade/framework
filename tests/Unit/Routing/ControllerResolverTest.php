<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Routing;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\RouteMatch;
use Lemonade\Framework\View\ViewRendererInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

final class ControllerResolverTest extends TestCase
{
    public function testStringIntParamIsCastedToInt(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'intAction'), ['id' => '123']),
            $this->request(),
        );

        self::assertSame('123', (string) $response->getBody());
    }

    public function testInvalidIntStringThrowsRuntimeException(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'intAction'), ['id' => 'abc']),
            $this->request(),
        );
    }

    public function testStringFloatParamIsCastedToFloat(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'floatAction'), ['value' => '12.5']),
            $this->request(),
        );

        self::assertSame('12.5', (string) $response->getBody());
    }

    public function testInvalidFloatStringThrowsRuntimeException(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'floatAction'), ['value' => 'abc']),
            $this->request(),
        );
    }

    public function testBoolTrueStringCastsToTrue(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'boolAction'), ['flag' => 'true']),
            $this->request(),
        );

        self::assertSame('true', (string) $response->getBody());
    }

    public function testBoolFalseStringCastsToFalse(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'boolAction'), ['flag' => 'false']),
            $this->request(),
        );

        self::assertSame('false', (string) $response->getBody());
    }

    public function testBoolOneStringCastsToTrue(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'boolAction'), ['flag' => '1']),
            $this->request(),
        );

        self::assertSame('true', (string) $response->getBody());
    }

    public function testBoolZeroStringCastsToFalse(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'boolAction'), ['flag' => '0']),
            $this->request(),
        );

        self::assertSame('false', (string) $response->getBody());
    }

    public function testInvalidBoolStringThrowsRuntimeException(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'boolAction'), ['flag' => 'yes']),
            $this->request(),
        );
    }

    public function testStringParamRemainsString(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(CastingController::class, 'stringAction'), ['value' => 'hello']),
            $this->request(),
        );

        self::assertSame('hello', (string) $response->getBody());
    }

    public function testPsrStyleControllerWithoutInheritanceCanReturnResponseInterface(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PsrStyleController::class, 'show')),
            $this->request(),
        );

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('psr-style', (string) $response->getBody());
    }

    public function testPlainControllerConstructorDependencyIsAutowired(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PlainWithDependencyController::class, 'show')),
            $this->request(),
        );

        self::assertSame('service-payload', (string) $response->getBody());
    }

    public function testPlainControllerReceivesCurrentRequestInActionParameter(): void
    {
        $resolver = $this->resolver();
        $request = (new Psr17Factory())->createServerRequest('GET', '/search?q=lemonade')
            ->withQueryParams(['q' => 'lemonade']);

        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PlainWithRequestController::class, 'index')),
            $request,
        );

        self::assertSame('lemonade', (string) $response->getBody());
    }

    public function testConstructorInjectedRequestUsesCurrentRequestAcrossDispatchCycles(): void
    {
        $container = new Container();
        $psr17 = new Psr17Factory();

        $container->singleton(ResponseFactoryInterface::class, $psr17);
        $container->singleton(StreamFactoryInterface::class, $psr17);

        $firstRequest = $psr17->createServerRequest('GET', '/first');
        $firstScope = $container->beginScope(\Lemonade\Framework\Container\ScopeKind::Request);
        $firstScope->bindScopedInstance(\Psr\Http\Message\ServerRequestInterface::class, $firstRequest);
        $firstResponse = (new ControllerResolver($firstScope, new Benchmark()))->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PlainWithConstructorRequestController::class, 'index')),
            $firstRequest,
        );
        $firstScope->close();

        $secondRequest = $psr17->createServerRequest('GET', '/second');
        $secondScope = $container->beginScope(\Lemonade\Framework\Container\ScopeKind::Request);
        $secondScope->bindScopedInstance(\Psr\Http\Message\ServerRequestInterface::class, $secondRequest);
        $secondResponse = (new ControllerResolver($secondScope, new Benchmark()))->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PlainWithConstructorRequestController::class, 'index')),
            $secondRequest,
        );
        $secondScope->close();

        self::assertSame('/first', (string) $firstResponse->getBody());
        self::assertSame('/second', (string) $secondResponse->getBody());
    }

    public function testPlainControllerRouteParamIsPassedAndCasted(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PlainWithRouteParamController::class, 'show'), ['id' => '42']),
            $this->request(),
        );

        self::assertSame('42', (string) $response->getBody());
    }

    public function testPlainControllerCanInjectResponses(): void
    {
        $resolver = $this->resolver();
        $response = $resolver->handle(
            new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(ResponsesController::class, 'index')),
            $this->request(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('<h1>Hello</h1>', (string) $response->getBody());
    }

    public function testPlainControllerCanInjectScopedViewRenderer(): void
    {
        $container = new Container();
        $factory = new Psr17Factory();
        $container->singleton(ResponseFactoryInterface::class, $factory);
        $container->singleton(StreamFactoryInterface::class, $factory);
        $container->scoped(
            ViewRendererInterface::class,
            static fn(): ViewRendererInterface => new ResolverViewRendererStub($factory),
        );
        $scope = $container->beginScope(ScopeKind::Request);

        try {
            $response = (new ControllerResolver($scope, new Benchmark()))->handle(
                new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(ViewRendererController::class, 'index')),
                $factory->createServerRequest('GET', '/page'),
            );

            self::assertSame(207, $response->getStatusCode());
            self::assertSame('rendered page', (string) $response->getBody());
        } finally {
            $scope->close();
        }
    }

    public function testMissingActionThrowsRuntimeExceptionForPlainController(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Action "' . PlainWithDependencyController::class . '::missing" not found.');
        $resolver->handle(new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PlainWithDependencyController::class, 'missing')), $this->request());
    }

    public function testMissingActionThrowsRuntimeExceptionForResponsesController(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Action "' . ResponsesController::class . '::missing" not found.');
        $resolver->handle(new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(ResponsesController::class, 'missing')), $this->request());
    }

    /**
     * @dataProvider nonPublicActionProvider
     */
    public function testNonPublicActionThrowsBeforeInvocation(string $action): void
    {
        NonPublicActionController::$invoked = false;
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller action "' . NonPublicActionController::class . '::' . $action . '" must be public.');

        try {
            $resolver->handle(new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(NonPublicActionController::class, $action)), $this->request());
        } finally {
            self::assertFalse(NonPublicActionController::$invoked);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function nonPublicActionProvider(): iterable
    {
        yield 'protected action' => ['protectedAction'];
        yield 'private action' => ['privateAction'];
    }

    public function testInvalidReturnValueThrowsRuntimeExceptionForPlainController(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller action result must be scalar|stringable|null or');
        $resolver->handle(new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PlainInvalidReturnController::class, 'index')), $this->request());
    }

    public function testInvalidReturnValueThrowsRuntimeExceptionForLegacyController(): void
    {
        $resolver = $this->resolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Controller action result must be scalar|stringable|null or');
        $resolver->handle(new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(LegacyInvalidReturnController::class, 'index')), $this->request());
    }

    public function testBenchmarkMarksAreEmittedWhenBenchmarkServiceIsBound(): void
    {
        $container = new Container();
        $psr17 = new Psr17Factory();
        $benchmark = new Benchmark();
        $benchmark->start();

        $container->singleton(ResponseFactoryInterface::class, $psr17);
        $container->singleton(StreamFactoryInterface::class, $psr17);
        $container->singleton(Benchmark::class, $benchmark);

        $resolver = new ControllerResolver($container, $benchmark);
        $resolver->handle(new RouteMatch(\Lemonade\Framework\Routing\ControllerAction::for(PsrStyleController::class, 'show')), $this->request());

        $run = $benchmark->current();
        self::assertNotNull($run);
        $markNames = array_map(static fn(array $mark): string => (string) $mark['name'], $run->marks());

        self::assertContains('controller_resolve_start', $markNames);
        self::assertContains('controller_resolved', $markNames);
        self::assertContains('controller_action_start', $markNames);
        self::assertContains('controller_action_finished', $markNames);
        self::assertContains('response_created', $markNames);
    }

    private function resolver(): ControllerResolver
    {
        $container = new Container();
        $psr17 = new Psr17Factory();

        $container->singleton(ResponseFactoryInterface::class, $psr17);
        $container->singleton(StreamFactoryInterface::class, $psr17);

        return new ControllerResolver($container, new Benchmark());
    }

    private function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/');
    }
}

final class CastingController
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

final class PsrStyleController
{
    public function show(): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse(202)
            ->withBody($factory->createStream('psr-style'));
    }
}

final class PlainWithDependencyController
{
    public function __construct(
        private readonly PlainDependencyService $service,
    ) {}

    public function show(): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse(200)
            ->withBody($factory->createStream($this->service->value()));
    }
}

final class PlainWithRequestController
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryValue = $request->getQueryParams()['q'] ?? '';
        $query = is_scalar($queryValue) ? (string) $queryValue : '';
        $factory = new Psr17Factory();

        return $factory->createResponse(200)
            ->withBody($factory->createStream($query));
    }
}

final class PlainWithConstructorRequestController
{
    public function __construct(
        private readonly ServerRequestInterface $request,
    ) {}

    public function index(): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse(200)
            ->withBody($factory->createStream($this->request->getUri()->getPath()));
    }
}

final class PlainWithRouteParamController
{
    public function show(int $id): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse(200)
            ->withBody($factory->createStream((string) $id));
    }
}

final class ResponsesController
{
    public function __construct(
        private readonly Responses $responses,
    ) {}

    public function index(): ResponseInterface
    {
        return $this->responses->html('<h1>Hello</h1>');
    }
}

final class ViewRendererController
{
    public function __construct(
        private readonly ViewRendererInterface $views,
    ) {}

    public function index(): ResponseInterface
    {
        return $this->views->render('page', status: 207);
    }
}

final class ResolverViewRendererStub implements ViewRendererInterface
{
    public function __construct(
        private readonly Psr17Factory $factory,
    ) {}

    public function render(string $template, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->factory->createResponse($status)->withBody($this->factory->createStream($this->content($template, $data)));
    }

    public function content(string $template, array $data = []): string
    {
        return 'rendered ' . $template;
    }
}

final class PlainInvalidReturnController
{
    /**
     * @return array<string, string>
     */
    public function index(): array
    {
        return ['invalid' => 'return'];
    }
}

final class LegacyInvalidReturnController
{
    /**
     * @return array<string, string>
     */
    public function index(): array
    {
        return ['invalid' => 'return'];
    }
}

final class NonPublicActionController
{
    public static bool $invoked = false;

    protected function protectedAction(): string
    {
        self::$invoked = true;

        return 'protected';
    }

    // @phpstan-ignore-next-line
    private function privateAction(): string
    {
        self::$invoked = true;

        return 'private';
    }
}

final class PlainDependencyService
{
    public function value(): string
    {
        return 'service-payload';
    }
}
