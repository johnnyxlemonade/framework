<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\MiddlewarePipeline;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Psr\Psr17Factory;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkServiceProvider;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Support\ServiceLocator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

final class Framework
{
    private readonly Router $router;
    /**
     * @var list<callable(MiddlewareStack):void>
     */
    private array $middlewareConfigurators = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ApplicationContext $context,
    ) {
        ServiceLocator::setContainer($this->container);

        $this->router = new Router();

        $this->registerCoreServices();
    }

    private function registerCoreServices(): void
    {
        $this->container->singleton(ApplicationContext::class, $this->context);
        $this->container->singleton('context', $this->context);
        $this->container->singleton(Environment::class, $this->context->environment());

        $this->container->singleton(Config::class, new Config());
        $this->container->singleton('config', Config::class);
        $this->loadFrameworkDefaults();
        $this->container->singleton(ContainerInterface::class, $this->container);
        $this->container->singleton(Router::class, $this->router);

        $frameworkLogger = new NullLogger();
        $this->container->singleton(LoggerInterface::class, $frameworkLogger);
        $this->container->setDiagnosticLogger($frameworkLogger);

        $this->container->singleton(Psr17Factory::class, Psr17Factory::class);
        $this->container->singleton(ServerRequestFactory::class, ServerRequestFactory::class);
        $this->register(new BenchmarkServiceProvider());

        $this->config([
            'app' => [
                'base_path' => $this->context->basePath(),
                'env' => $this->context->environment()->value,
                'debug' => $this->context->debug(),
            ],
        ]);
    }

    private function loadFrameworkDefaults(): void
    {
        $defaultsFile = dirname(__DIR__) . '/Config/Framework.php';

        if (!is_file($defaultsFile)) {
            return;
        }

        $defaults = require $defaultsFile;

        if (!is_array($defaults)) {
            throw new RuntimeException(sprintf('Config file "%s" must return array.', $defaultsFile));
        }

        $normalized = [];
        foreach ($defaults as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        $this->config($normalized);
    }

    public function context(): ApplicationContext
    {
        return $this->context;
    }

    public function register(ServiceProviderInterface ...$providers): self
    {
        foreach ($providers as $provider) {
            $provider->register($this->container);
        }

        return $this;
    }

    public function routes(callable $builder): self
    {
        $this->configureRouterLocalizedRoutes();
        $builder($this->router);

        return $this;
    }

    public function routesFromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Routing file not found: %s', $file));
        }

        $loader = require $file;

        if (!is_callable($loader)) {
            throw new RuntimeException(
                sprintf('Routing file "%s" must return callable(Router $router): void', $file),
            );
        }

        $this->configureRouterLocalizedRoutes();
        $loader($this->router);

        return $this;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function config(array $config): self
    {
        $this->container->get(Config::class)->merge($config);

        return $this;
    }

    public function configFromFile(string $file): self
    {
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Config file not found: %s', $file));
        }

        $data = require $file;

        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Config file "%s" must return array.', $file));
        }

        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $this->config($normalized);
    }

    /**
     * @param callable(MiddlewareStack):void $configure
     */
    public function middleware(callable $configure): self
    {
        if ($this->container->isBound(MiddlewareStack::class)) {
            $configure($this->container->get(MiddlewareStack::class));
            return $this;
        }

        $this->middlewareConfigurators[] = $configure;

        return $this;
    }

    public function run(?ServerRequestInterface $request = null): ResponseInterface
    {
        $request ??= $this->container
            ->get(ServerRequestFactory::class)
            ->fromGlobals();

        /** @var Benchmark $benchmark */
        $benchmark = $this->container->get(Benchmark::class);
        $run = $benchmark->currentOrStart();
        $run->mark('request_received');

        $stack = $this->container->get(MiddlewareStack::class);
        $this->applyPendingMiddlewareConfiguration($stack);
        $middleware = $this->container
            ->get(MiddlewareResolver::class)
            ->resolve($stack->all());

        $pipeline = MiddlewarePipeline::create(
            $middleware,
            $this->container->get(DispatchRequestHandler::class),
        );

        $run->mark('middleware_enter');
        $response = $pipeline->handle($request);
        $run->mark('response_ready');

        return $response;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    private function applyPendingMiddlewareConfiguration(MiddlewareStack $stack): void
    {
        if ($this->middlewareConfigurators === []) {
            return;
        }

        foreach ($this->middlewareConfigurators as $configure) {
            $configure($stack);
        }

        $this->middlewareConfigurators = [];
    }

    private function configureRouterLocalizedRoutes(): void
    {
        $config = $this->container->get(Config::class);

        $namePrefix = $config->string('localization.url.localized_route_name_prefix');
        if (!is_string($namePrefix) || trim($namePrefix) === '') {
            $legacyPrefix = $config->string('localization.url.prefix_route_name');
            $namePrefix = is_string($legacyPrefix) && trim($legacyPrefix) !== '' ? $legacyPrefix : 'localized.';
        }
        $routePrefix = $config->string('localization.url.route_prefix');
        $localeParameter = $config->string('localization.url.locale_parameter', 'locale');

        $this->router->configureLocalizedRoutes(
            $namePrefix,
            $routePrefix,
            $localeParameter ?? 'locale',
        );
    }
}
