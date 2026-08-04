<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Cli\Config\CommandsConfig;
use Lemonade\Framework\Cli\Config\CommandsConfigDefinition;
use Lemonade\Framework\Cli\Config\CommandsConfigResolver;
use Lemonade\Framework\Container\Config\ContainerConfig;
use Lemonade\Framework\Container\Config\ContainerConfigDefinition;
use Lemonade\Framework\Container\Config\ContainerConfigResolver;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\AppConfigResolver;
use Lemonade\Framework\Core\Config\ConfigFileLoader;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Config\FrameworkConfig;
use Lemonade\Framework\Core\Config\FrameworkConfigDefinition;
use Lemonade\Framework\Core\Config\FrameworkConfigResolver;
use Lemonade\Framework\Core\Config\ProvidersConfig;
use Lemonade\Framework\Core\Config\ProvidersConfigDefinition;
use Lemonade\Framework\Core\Config\ProvidersConfigResolver;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\MiddlewarePipeline;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Psr\Psr17Factory;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkServiceProvider;
use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Main bootstrap and runtime facade for the Lemonade Framework HTTP application layer.
 *
 * The facade coordinates service provider registration, configuration definitions,
 * localized route setup, middleware configuration, and execution of the PSR-15
 * request pipeline. It operates on top of the supplied dependency injection
 * container and application context.
 */
final class Framework
{
    private readonly Router $router;
    /**
     * @var list<callable(MiddlewareStack):void>
     */
    private array $middlewareConfigurators = [];

    /**
     * Creates a framework runtime bound to the provided container and application context.
     *
     * The constructor initializes the framework router and registers the core
     * framework services required for configuration loading and request handling.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ApplicationContext $context,
    ) {
        $this->router = new Router();

        $this->registerCoreServices();
    }

    private function registerCoreServices(): void
    {
        $this->container->singleton(ApplicationContext::class, $this->context);
        $this->container->singleton('context', $this->context);
        $this->container->singleton(Environment::class, $this->context->environment());

        $this->container->singleton(Config::class, new Config());
        $this->container->singleton(ConfigDefinitionRegistry::class, new ConfigDefinitionRegistry());
        $this->container->singleton('config', Config::class);
        $this->container->singleton(ContainerConfigResolver::class, ContainerConfigResolver::class);
        $this->container->singleton(ContainerConfig::class, static function (ContainerInterface $container): ContainerConfig {
            return $container
                ->get(ContainerConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ContainerConfigDefinition::moduleKey(),
                    ContainerConfigDefinition::class,
                ));
        });
        $this->container->singleton(AppConfigResolver::class, AppConfigResolver::class);
        $this->container->singleton(AppConfig::class, static function (ContainerInterface $container): AppConfig {
            return $container
                ->get(AppConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    AppConfigDefinition::moduleKey(),
                    AppConfigDefinition::class,
                ));
        });
        $this->container->singleton(FrameworkConfigResolver::class, FrameworkConfigResolver::class);
        $this->container->singleton(FrameworkConfig::class, static function (ContainerInterface $container): FrameworkConfig {
            return $container
                ->get(FrameworkConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    FrameworkConfigDefinition::moduleKey(),
                    FrameworkConfigDefinition::class,
                ));
        });
        $this->container->singleton(ProvidersConfigResolver::class, ProvidersConfigResolver::class);
        $this->container->singleton(ProvidersConfig::class, static function (ContainerInterface $container): ProvidersConfig {
            return $container
                ->get(ProvidersConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    ProvidersConfigDefinition::moduleKey(),
                    ProvidersConfigDefinition::class,
                ));
        });
        $this->container->singleton(CommandsConfigResolver::class, CommandsConfigResolver::class);
        $this->container->singleton(CommandsConfig::class, static function (ContainerInterface $container): CommandsConfig {
            return $container
                ->get(CommandsConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    CommandsConfigDefinition::moduleKey(),
                    CommandsConfigDefinition::class,
                ));
        });
        $this->loadFrameworkDefaults();
        $this->container->singleton(ContainerInterface::class, $this->container);
        $this->container->singleton(Router::class, $this->router);

        $frameworkLogger = new NullLogger();
        $this->container->singleton(LoggerInterface::class, $frameworkLogger);
        $this->container->setDiagnosticLogger($frameworkLogger);

        $this->container->singleton(Psr17Factory::class, Psr17Factory::class);
        $this->container->singleton(ServerRequestFactory::class, ServerRequestFactory::class);
        $this->register(new BenchmarkServiceProvider());

        $this->config(
            ContainerConfigDefinition::create()
                ->autowireFallbackWarning($this->context->isDevelopment()),
            AppConfigDefinition::create()
                ->basePath($this->context->basePath())
                ->env($this->context->environment()->value)
                ->debug($this->context->debug()),
        );
    }

    private function loadFrameworkDefaults(): void
    {
        $manifestPath = dirname(__DIR__) . '/Config/Config.php';

        if (!is_file($manifestPath)) {
            throw new RuntimeException(sprintf('Framework config manifest not found: %s', $manifestPath));
        }

        $manifest = require $manifestPath;
        if (!is_array($manifest)) {
            throw new RuntimeException(sprintf('Framework config manifest "%s" must return array.', $manifestPath));
        }

        $shared = $manifest['shared'] ?? null;
        $http = $manifest['http'] ?? null;
        $cli = $manifest['cli'] ?? null;
        if (!is_array($shared) || !is_array($http) || !is_array($cli)) {
            throw new RuntimeException(sprintf(
                'Framework config manifest "%s" must contain array keys "shared", "http", and "cli".',
                $manifestPath,
            ));
        }

        foreach ($this->normalizeManifestSection($shared, $manifestPath) as $fileName) {
            $defaultsFile = dirname(__DIR__) . '/Config/' . $fileName;
            if (!is_file($defaultsFile)) {
                continue;
            }

            $this->configFromFile($defaultsFile);
        }
    }

    /**
     * @param array<mixed> $section
     * @return list<string>
     */
    private function normalizeManifestSection(array $section, string $manifestPath): array
    {
        $normalized = [];

        foreach ($section as $fileName) {
            if (!is_string($fileName) || trim($fileName) === '') {
                throw new RuntimeException(sprintf(
                    'Framework config manifest "%s" contains invalid file name.',
                    $manifestPath,
                ));
            }

            $normalized[] = trim($fileName);
        }

        return $normalized;
    }

    /**
     * Returns the application context used during framework bootstrap.
     */
    public function context(): ApplicationContext
    {
        return $this->context;
    }

    /**
     * Registers one or more service providers in the order they are supplied.
     *
     * @return $this Returns the same framework instance for fluent chaining.
     */
    public function register(ServiceProviderInterface ...$providers): self
    {
        foreach ($providers as $provider) {
            $provider->register($this->container);
        }

        return $this;
    }

    /**
     * Configures application routes through the framework router.
     *
     * Localized route settings are applied before the callback is invoked.
     *
     * @param callable(\Lemonade\Framework\Routing\Router): void $builder
     * @return $this Returns the same framework instance for fluent chaining.
     */
    public function routes(callable $builder): self
    {
        $this->configureRouterLocalizedRoutes();
        $builder($this->router);

        return $this;
    }

    /**
     * Loads route definitions from a PHP file returning a router configurator callback.
     *
     * Localized route settings are applied before the loaded callback is invoked.
     *
     * @return $this Returns the same framework instance for fluent chaining.
     *
     * @throws RuntimeException When the routing file does not exist.
     * @throws RuntimeException When the routing file does not return a callable accepting the router.
     */
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
     * Registers configuration definitions and merges their serialized data into runtime config state.
     *
     * Definitions are processed in the order they are supplied.
     *
     * @return $this Returns the same framework instance for fluent chaining.
     */
    public function config(ConfigDefinitionInterface ...$definitions): self
    {
        $registry = $this->container->get(ConfigDefinitionRegistry::class);
        $state = $this->container->get(Config::class);

        foreach ($definitions as $definition) {
            $registry->addDefinition($definition);
            $state->merge([
                $definition::moduleKey() => $definition->toArray(),
            ]);
        }

        return $this;
    }

    /**
     * Loads a configuration definition from file through the config file loader.
     *
     * When provided, the root key selects the root section to load from the file.
     *
     * @return $this Returns the same framework instance for fluent chaining.
     */
    public function configFromFile(string $file, ?string $rootKey = null): self
    {
        return $this->config(
            (new ConfigFileLoader())->load($file, $rootKey),
        );
    }

    /**
     * Configures the framework middleware stack.
     *
     * If the middleware stack is already available in the container, the callback
     * is applied immediately. Otherwise, it is deferred until just before the
     * request pipeline is executed.
     *
     * @param callable(MiddlewareStack):void $configure
     * @return $this Returns the same framework instance for fluent chaining.
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

    /**
     * Runs an HTTP request through the configured middleware pipeline.
     *
     * When no request is supplied, a server request is created from global PHP
     * state through the server request factory. Any deferred middleware
     * configuration is applied before middleware is resolved, and the pipeline
     * terminates in the dispatch request handler.
     */
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

    /**
     * Returns the framework dependency injection container.
     */
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
        $config = $this->container->get(LocalizationConfig::class);

        $this->router->configureLocalizedRoutes(
            $config->url->localizedRouteNamePrefix,
            $config->url->routePrefix,
            $config->url->localeParameter,
            $config->supportedLocales,
        );
    }

}
