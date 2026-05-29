<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Component\ComponentServiceProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\CoreServiceProvider;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Database\DatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Mysql\MysqlDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Odbc\OdbcDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteDatabaseServiceProvider;
use Lemonade\Framework\Debug\DebugServiceProvider;
use Lemonade\Framework\Discovery\DiscoveryServiceProvider;
use Lemonade\Framework\Event\EventServiceProvider;
use Lemonade\Framework\Http\HttpServiceProvider;
use Lemonade\Framework\Http\Middleware\CorsMiddleware;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Localization\LocalizationServiceProvider;
use Lemonade\Framework\Queue\QueueServiceProvider;
use Lemonade\Framework\Routing\RoutingServiceProvider;
use Lemonade\Framework\Security\SecurityServiceProvider;
use Lemonade\Framework\Session\SessionServiceProvider;
use Lemonade\Framework\Upload\UploadServiceProvider;
use Lemonade\Framework\Validation\ValidationServiceProvider;
use Lemonade\Framework\View\ViewServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class FrameworkTest extends TestCase
{
    public function testFrameworkLoadsDefaultFrameworkProvidersConfig(): void
    {
        $framework = $this->framework();
        $config = $framework->container()->get(Config::class);
        $providers = $config->get('framework.providers');

        self::assertIsArray($providers);
        self::assertSame([
            LocalizationServiceProvider::class,
            RoutingServiceProvider::class,
            DiscoveryServiceProvider::class,
            SecurityServiceProvider::class,
            DatabaseServiceProvider::class,
            MysqlDatabaseServiceProvider::class,
            OdbcDatabaseServiceProvider::class,
            PdoDatabaseServiceProvider::class,
            SqliteDatabaseServiceProvider::class,
            SessionServiceProvider::class,
            ComponentServiceProvider::class,
            ValidationServiceProvider::class,
            UploadServiceProvider::class,
            DebugServiceProvider::class,
            ViewServiceProvider::class,
            EventServiceProvider::class,
            QueueServiceProvider::class,
        ], $providers);
    }

    public function testAppConfigCanReplaceFrameworkProvidersList(): void
    {
        $framework = $this->framework();
        $config = $framework->container()->get(Config::class);

        $framework->config([
            'framework' => [
                'providers' => [
                    RoutingServiceProvider::class,
                ],
            ],
        ]);

        self::assertSame(
            [RoutingServiceProvider::class],
            $config->get('framework.providers'),
        );
    }

    public function testFrameworkDefaultsExposeCorsDisabled(): void
    {
        $framework = $this->framework();
        $config = $framework->container()->get(Config::class);

        self::assertFalse($config->bool('cors.enabled'));
    }

    public function testAppConfigOverridesCorsDefaultsAndNestedValuesAreAvailable(): void
    {
        $framework = $this->framework();
        $config = $framework->container()->get(Config::class);

        self::assertFalse($config->bool('cors.enabled'));

        $framework->config([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['http://localhost:3000'],
                'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            ],
        ]);

        self::assertTrue($config->bool('cors.enabled'));
        self::assertSame(['http://localhost:3000'], $config->array('cors.allowed_origins'));
        self::assertSame(['GET', 'POST', 'OPTIONS'], $config->array('cors.allowed_methods'));
    }

    public function testAppConfigCanPartiallyOverrideCorsBecauseMergeIsRecursive(): void
    {
        $framework = $this->framework();
        $config = $framework->container()->get(Config::class);

        $framework->config([
            'cors' => [
                'enabled' => true,
            ],
        ]);

        self::assertTrue($config->bool('cors.enabled'));
        self::assertSame([], $config->array('cors.allowed_origins'));
        self::assertSame([], $config->array('cors.allowed_methods'));
    }

    public function testFrameworkExposesDefaultMiddlewareStack(): void
    {
        $framework = $this->framework();
        $framework->register(new HttpServiceProvider());

        $stack = $framework->container()->get(MiddlewareStack::class);

        self::assertSame([
            \Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware::class,
            \Lemonade\Framework\Http\Middleware\BenchmarkMiddleware::class,
            \Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware::class,
            \Lemonade\Framework\Http\Middleware\CorsMiddleware::class,
            \Lemonade\Framework\Http\Middleware\PoweredByMiddleware::class,
            \Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware::class,
            \Lemonade\Framework\Http\Middleware\OptionsMiddleware::class,
        ], $stack->all());
    }

    public function testRunBuildsPipelineFromConfiguredMiddlewareStack(): void
    {
        $framework = $this->framework();
        $framework->register(new HttpServiceProvider());

        $factory = new Psr17Factory();
        $container = $framework->container();
        $container->singleton(FrameworkStackTraceMiddleware::class, FrameworkStackTraceMiddleware::class);
        $container->singleton(FrameworkStackTerminalMiddleware::class, FrameworkStackTerminalMiddleware::class);

        $framework->middleware(static function (MiddlewareStack $stack): void {
            $stack->remove(\Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\BenchmarkMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\CorsMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\PoweredByMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\OptionsMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware::class)
                ->add(FrameworkStackTraceMiddleware::class)
                ->add(FrameworkStackTerminalMiddleware::class);
        });

        $response = $framework->run($factory->createServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('terminal', (string) $response->getBody());
        self::assertSame('1', $response->getHeaderLine('X-Trace-Middleware'));
    }

    public function testMiddlewareQueuedBeforeHttpServiceProviderRegistrationIsAppliedInRun(): void
    {
        $framework = $this->framework();
        $factory = new Psr17Factory();
        $framework->container()->singleton(FrameworkQueuedTerminalMiddleware::class, FrameworkQueuedTerminalMiddleware::class);

        $framework->middleware(static function (MiddlewareStack $stack): void {
            $stack->remove(\Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\BenchmarkMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\CorsMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\PoweredByMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\OptionsMiddleware::class)
                ->add(FrameworkQueuedTerminalMiddleware::class);
        });

        $framework->register(new HttpServiceProvider());

        $response = $framework->run($factory->createServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('queued-terminal', (string) $response->getBody());
    }

    public function testPendingMiddlewareConfiguratorsAreAppliedOnlyOnce(): void
    {
        $framework = $this->framework();
        $factory = new Psr17Factory();
        $framework->container()->singleton(FrameworkQueuedTerminalMiddleware::class, FrameworkQueuedTerminalMiddleware::class);

        $calls = 0;
        $framework->middleware(static function (MiddlewareStack $stack) use (&$calls): void {
            $calls++;
            $stack->remove(\Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\BenchmarkMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\CorsMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\PoweredByMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\OptionsMiddleware::class)
                ->add(FrameworkQueuedTerminalMiddleware::class);
        });

        $framework->register(new HttpServiceProvider());
        $framework->run($factory->createServerRequest('GET', '/'));
        $framework->run($factory->createServerRequest('GET', '/'));

        self::assertSame(1, $calls);
    }

    public function testMiddlewareCalledAfterFirstRunAppliesDirectlyToExistingStack(): void
    {
        $framework = $this->framework();
        $framework->register(new HttpServiceProvider());
        $factory = new Psr17Factory();
        $container = $framework->container();
        $container->singleton(FrameworkMutableTerminalMiddleware::class, FrameworkMutableTerminalMiddleware::class);
        $container->singleton(FrameworkMutableTraceMiddleware::class, FrameworkMutableTraceMiddleware::class);

        $framework->middleware(static function (MiddlewareStack $stack): void {
            $stack->remove(\Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\BenchmarkMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\CorsMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\PoweredByMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\OptionsMiddleware::class)
                ->add(FrameworkMutableTerminalMiddleware::class);
        });

        $first = $framework->run($factory->createServerRequest('GET', '/'));
        self::assertSame('', $first->getHeaderLine('X-Mutable-Trace'));

        $framework->middleware(static function (MiddlewareStack $stack): void {
            $stack->insertBefore(FrameworkMutableTerminalMiddleware::class, FrameworkMutableTraceMiddleware::class);
        });

        $second = $framework->run($factory->createServerRequest('GET', '/'));
        self::assertSame('1', $second->getHeaderLine('X-Mutable-Trace'));
    }

    public function testMiddlewareCanUseAddRemoveInsertBeforeAndInsertAfterViaFrameworkApi(): void
    {
        $framework = $this->framework();
        $framework->register(new HttpServiceProvider());
        $factory = new Psr17Factory();
        $container = $framework->container();
        $container->singleton(FrameworkOrderStartMiddleware::class, FrameworkOrderStartMiddleware::class);
        $container->singleton(FrameworkOrderMiddleMiddleware::class, FrameworkOrderMiddleMiddleware::class);
        $container->singleton(FrameworkOrderEndMiddleware::class, FrameworkOrderEndMiddleware::class);
        $container->singleton(FrameworkOrderInsertedBeforeMiddleware::class, FrameworkOrderInsertedBeforeMiddleware::class);
        $container->singleton(FrameworkOrderInsertedAfterMiddleware::class, FrameworkOrderInsertedAfterMiddleware::class);
        $container->singleton(FrameworkOrderTerminalMiddleware::class, FrameworkOrderTerminalMiddleware::class);

        $framework->middleware(static function (MiddlewareStack $stack): void {
            $stack->remove(\Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\BenchmarkMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\CorsMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\PoweredByMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\OptionsMiddleware::class)
                ->add(FrameworkOrderStartMiddleware::class)
                ->add(FrameworkOrderEndMiddleware::class)
                ->insertBefore(FrameworkOrderEndMiddleware::class, FrameworkOrderMiddleMiddleware::class)
                ->insertBefore(FrameworkOrderMiddleMiddleware::class, FrameworkOrderInsertedBeforeMiddleware::class)
                ->insertAfter(FrameworkOrderMiddleMiddleware::class, FrameworkOrderInsertedAfterMiddleware::class)
                ->add(FrameworkOrderTerminalMiddleware::class);
        });

        $response = $framework->run($factory->createServerRequest('GET', '/'));

        self::assertSame('end,after,middle,before,start', $response->getHeaderLine('X-Order'));
    }

    public function testCorsHeaderIsAddedWhenCorsEnabledViaAppConfig(): void
    {
        $framework = $this->framework();
        $framework->register(new CoreServiceProvider());
        $framework->register(new HttpServiceProvider());
        $factory = new Psr17Factory();
        $container = $framework->container();
        $container->singleton(FrameworkCorsTerminalMiddleware::class, FrameworkCorsTerminalMiddleware::class);

        $framework->config([
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['http://localhost:3000'],
                'allowed_methods' => ['GET', 'OPTIONS'],
                'allowed_headers' => ['Content-Type'],
            ],
        ]);

        $framework->middleware(static function (MiddlewareStack $stack): void {
            $stack->remove(\Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\BenchmarkMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\CorsMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\PoweredByMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware::class)
                ->remove(\Lemonade\Framework\Http\Middleware\OptionsMiddleware::class)
                ->add(CorsMiddleware::class)
                ->add(FrameworkCorsTerminalMiddleware::class);
        });

        $response = $framework->run(
            $factory->createServerRequest('GET', '/')
                ->withHeader('Origin', 'http://localhost:3000'),
        );

        self::assertSame('http://localhost:3000', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    private function framework(): Framework
    {
        $container = new Container();
        $context = new ApplicationContext(
            Environment::Testing,
            new Path(__DIR__),
            DebugMode::disabled(),
        );

        return new Framework($container, $context);
    }
}

final class FrameworkStackTraceMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)
            ->withHeader('X-Trace-Middleware', '1');
    }
}

final class FrameworkStackTerminalMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        unset($request, $handler);
        $factory = new Psr17Factory();

        return $factory->createResponse(200)
            ->withBody($factory->createStream('terminal'));
    }
}

final class FrameworkQueuedTerminalMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        unset($request, $handler);
        $factory = new Psr17Factory();

        return $factory->createResponse(200)
            ->withBody($factory->createStream('queued-terminal'));
    }
}

final class FrameworkMutableTerminalMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        unset($request, $handler);
        $factory = new Psr17Factory();

        return $factory->createResponse(200)
            ->withBody($factory->createStream('mutable-terminal'));
    }
}

final class FrameworkMutableTraceMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Mutable-Trace', '1');
    }
}

final class FrameworkOrderStartMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return frameworkOrderAppend($handler->handle($request), 'start');
    }
}

final class FrameworkOrderMiddleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return frameworkOrderAppend($handler->handle($request), 'middle');
    }
}

final class FrameworkOrderEndMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return frameworkOrderAppend($handler->handle($request), 'end');
    }
}

final class FrameworkOrderInsertedBeforeMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return frameworkOrderAppend($handler->handle($request), 'before');
    }
}

final class FrameworkOrderInsertedAfterMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return frameworkOrderAppend($handler->handle($request), 'after');
    }
}

final class FrameworkOrderTerminalMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        unset($request, $handler);
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream('order-terminal'));
    }
}

final class FrameworkCorsTerminalMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        unset($request, $handler);
        $factory = new Psr17Factory();

        return $factory->createResponse(200)->withBody($factory->createStream('cors-terminal'));
    }
}

function frameworkOrderAppend(ResponseInterface $response, string $token): ResponseInterface
{
    $current = $response->getHeaderLine('X-Order');
    if ($current === '') {
        return $response->withHeader('X-Order', $token);
    }

    return $response->withHeader('X-Order', $current . ',' . $token);
}
