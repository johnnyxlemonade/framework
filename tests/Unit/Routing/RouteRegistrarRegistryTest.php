<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Routing;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Localization\LocalizationServiceProvider;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\RouteRegistrarInterface;
use Lemonade\Framework\Routing\RouteRegistrarRegistry;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RouteRegistrarRegistryTest extends TestCase
{
    public function testRegistersRegistrarsByPriorityThenId(): void
    {
        $log = new RouteRegistrarLog();
        $registry = new RouteRegistrarRegistry();
        $router = new Router();

        $registry->register(new RecordingRouteRegistrar('z', 10, $log));
        $registry->register(new RecordingRouteRegistrar('b', 20, $log));
        $registry->register(new RecordingRouteRegistrar('a', 10, $log));
        $registry->registerRoutes($router);

        self::assertSame(['a', 'z', 'b'], $log->ids());
    }

    public function testRejectsDuplicateRegistrarId(): void
    {
        $registry = new RouteRegistrarRegistry();
        $log = new RouteRegistrarLog();
        $registry->register(new RecordingRouteRegistrar('articles', 10, $log));

        $this->expectException(\LogicException::class);
        $registry->register(new RecordingRouteRegistrar('articles', 20, $log));
    }

    public function testRejectsEmptyRegistrarIdAfterNormalization(): void
    {
        $registry = new RouteRegistrarRegistry();

        $this->expectException(\LogicException::class);
        $registry->register(new RecordingRouteRegistrar('  ', 10, new RouteRegistrarLog()));
    }

    public function testRejectsRegistrationAndExecutionAfterFreeze(): void
    {
        $registry = new RouteRegistrarRegistry();
        $registry->freeze();

        self::assertTrue($registry->isFrozen());

        try {
            $registry->register(new RecordingRouteRegistrar('articles', 10, new RouteRegistrarLog()));
            self::fail('Expected frozen registry to reject a registrar.');
        } catch (\LogicException $exception) {
            self::assertSame('Route registrar registry is frozen.', $exception->getMessage());
        }

        $this->expectException(\LogicException::class);
        $registry->registerRoutes(new Router());
    }

    public function testRegistrarFailureIncludesRegistrarId(): void
    {
        $registry = new RouteRegistrarRegistry();
        $registry->register(new FailingRouteRegistrar('broken.routes'));

        try {
            $registry->registerRoutes(new Router());
            self::fail('Expected route registrar failure.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('broken.routes', $exception->getMessage());
            self::assertSame(0, $exception->getCode());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
            self::assertSame('Registrar failure.', $exception->getPrevious()->getMessage());
        }
    }

    public function testFrameworkExecutesProviderRoutesAfterApplicationRoutesThenFreezesBoth(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(sys_get_temp_dir()),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);
        $framework->register(new LocalizationServiceProvider());
        $log = new RouteRegistrarLog();

        $container->singleton('route.registrar.articles', new RecordingRouteRegistrar('articles', 10, $log));
        $container->tag('route.registrar.articles', RouteRegistrarInterface::class);
        $framework->routes(static function (Router $router): void {
            $router->get('/application', 'ApplicationController@index');
        });
        $framework->finalizeRoutes();

        $router = $container->get(Router::class);
        $registry = $container->get(RouteRegistrarRegistry::class);
        self::assertSame(['articles'], $log->ids());
        self::assertTrue($registry->isFrozen());
        self::assertTrue($router->isFrozen());
        self::assertSame(
            'App\\Controllers\\ApplicationController',
            $router->match(new ServerRequest('GET', '/application'))->controller(),
        );
        self::assertSame(
            'App\\Controllers\\ArticlesController',
            $router->match(new ServerRequest('GET', '/provider/articles'))->controller(),
        );
    }

    public function testFrameworkSortsTaggedRegistrarsByPriorityThenId(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(sys_get_temp_dir()),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);
        $log = new RouteRegistrarLog();

        $container->singleton('route.registrar.z', new RecordingRouteRegistrar('z', 10, $log));
        $container->singleton('route.registrar.b', new RecordingRouteRegistrar('b', 20, $log));
        $container->singleton('route.registrar.a', new RecordingRouteRegistrar('a', 10, $log));
        $container->tag('route.registrar.z', RouteRegistrarInterface::class);
        $container->tag('route.registrar.b', RouteRegistrarInterface::class);
        $container->tag('route.registrar.a', RouteRegistrarInterface::class);

        $framework->finalizeRoutes();

        self::assertSame(['a', 'z', 'b'], $log->ids());
    }

    public function testTaggedRouteRegistrarMustImplementTheRouteRegistrarContract(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(sys_get_temp_dir()),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);
        $container->singleton('route.registrar.invalid', new \stdClass());
        $container->tag('route.registrar.invalid', RouteRegistrarInterface::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('route.registrar.invalid');
        $this->expectExceptionMessage(RouteRegistrarInterface::class);
        $framework->finalizeRoutes();
    }

    public function testExplicitAndTaggedRegistrarWithTheSameIdAreRejected(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(sys_get_temp_dir()),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);
        $log = new RouteRegistrarLog();
        $registry = $container->get(RouteRegistrarRegistry::class);
        $registry->register(new RecordingRouteRegistrar('duplicate', 10, $log));
        $container->singleton('route.registrar.duplicate', new RecordingRouteRegistrar('duplicate', 20, $log));
        $container->tag('route.registrar.duplicate', RouteRegistrarInterface::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('duplicate');
        $framework->finalizeRoutes();
    }

    public function testTagAddedAfterRouteFinalizationDoesNotMutateTheFrozenRouter(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(sys_get_temp_dir()),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);

        $framework->finalizeRoutes();

        $container->singleton('route.registrar.late', new RecordingRouteRegistrar('late', 10, new RouteRegistrarLog()));
        $container->tag('route.registrar.late', RouteRegistrarInterface::class);

        $router = $container->get(Router::class);
        self::assertTrue($router->isFrozen());

        $this->expectException(RouteNotFoundException::class);
        $router->match(new ServerRequest('GET', '/provider/late'));
    }
}

final class RouteRegistrarLog
{
    /**
     * @var list<string>
     */
    private array $ids = [];

    public function add(string $id): void
    {
        $this->ids[] = $id;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->ids;
    }
}

final class RecordingRouteRegistrar implements RouteRegistrarInterface
{
    public function __construct(
        private readonly string $registrarId,
        private readonly int $registrarPriority,
        private readonly RouteRegistrarLog $log,
    ) {}

    public function id(): string
    {
        return $this->registrarId;
    }

    public function priority(): int
    {
        return $this->registrarPriority;
    }

    public function registerRoutes(Router $router): void
    {
        $this->log->add($this->registrarId);
        $router->get('/provider/' . $this->registrarId, 'ArticlesController@index');
    }
}

final class FailingRouteRegistrar implements RouteRegistrarInterface
{
    public function __construct(
        private readonly string $registrarId,
    ) {}

    public function id(): string
    {
        return $this->registrarId;
    }

    public function priority(): int
    {
        return 10;
    }

    public function registerRoutes(Router $router): void
    {
        unset($router);

        throw new \RuntimeException('Registrar failure.');
    }
}
