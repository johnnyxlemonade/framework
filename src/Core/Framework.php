<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\Config\ContainerConfigDefinition;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\Config\AppConfigDefinition;
use Lemonade\Framework\Core\Config\ConfigFileLoader;
use Lemonade\Framework\Core\Config\CoreConfigurationServiceProvider;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Config\FrameworkDefaultsLoader;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Exception\InvalidRequestScopeException;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\MiddlewarePipeline;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Localization\Config\LocalizationConfig;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkServiceProvider;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRegistrarInterface;
use Lemonade\Framework\Routing\RouteRegistrarRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
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
    private readonly ServiceProviderLifecycle $providerLifecycle;
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
        $this->providerLifecycle = new ServiceProviderLifecycle($this->container);
        $this->router = new Router();

        $this->registerCoreServices();
    }

    private function registerCoreServices(): void
    {
        $this->container->singleton(ApplicationContext::class, $this->context);
        $this->container->singleton(Environment::class, $this->context->environment());

        $this->register(new CoreConfigurationServiceProvider());
        $this->config(...(new FrameworkDefaultsLoader())->load());
        $this->container->singleton(ContainerInterface::class, $this->container);
        $this->container->singleton(Router::class, $this->router);
        $this->container->singleton(RouteRegistrarRegistry::class, RouteRegistrarRegistry::class);

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
                ->publicPath($this->context->publicPath())
                ->env($this->context->environment()->value)
                ->debug($this->context->debug()),
        );
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
    public function register(object ...$providers): self
    {
        $this->providerLifecycle->register(...$providers);

        return $this;
    }

    /**
     * Compiles definitions and invokes bootable providers in registration order.
     */
    public function bootProviders(): void
    {
        $this->providerLifecycle->boot();
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
     * Finalizes application routing after providers and application composition routes.
     *
     * The kernel owns this lifecycle boundary. Providers only register typed route
     * registrars; application routing files only register application routes.
     */
    public function finalizeRoutes(): void
    {
        $registry = $this->container->get(RouteRegistrarRegistry::class);

        foreach ($this->container->tagged(RouteRegistrarInterface::class) as $serviceId => $registrar) {
            if (!$registrar instanceof RouteRegistrarInterface) {
                throw new RuntimeException(sprintf(
                    'Tagged service "%s" for tag "%s" must implement %s.',
                    $serviceId,
                    RouteRegistrarInterface::class,
                    RouteRegistrarInterface::class,
                ));
            }

            $registry->register($registrar);
        }

        $registry->registerRoutes($this->router);
        $registry->freeze();
        $this->router->freeze();
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
     * configuration is applied before middleware is resolved. This convenience
     * entrypoint creates and closes its own request scope; Kernel callers pass
     * their already-active scope to {@see runInScope()} instead.
     */
    public function run(?ServerRequestInterface $request = null): ResponseInterface
    {
        $request ??= $this->container
            ->get(ServerRequestFactory::class)
            ->fromGlobals();

        if (!$this->container instanceof ScopeFactoryInterface) {
            throw new RuntimeException(sprintf(
                'Framework container must implement %s to run an HTTP request.',
                ScopeFactoryInterface::class,
            ));
        }

        $scope = $this->container->beginScope(ScopeKind::Request);
        $scope->bindScopedInstance(ServerRequestInterface::class, $request);

        try {
            return $this->runInScope($scope, $request);
        } finally {
            $scope->close();
        }
    }

    /**
     * Runs an HTTP request in an already-active request scope.
     *
     * The scope must be {@see ScopeKind::Request}, must hold a scope-local
     * {@see ServerRequestInterface} binding, and that binding must be the same
     * object as the request argument. Kernel owns this integration boundary.
     *
     * @throws InvalidRequestScopeException When the supplied scope does not satisfy the request contract.
     */
    public function runInScope(ScopedContainerInterface $scope, ServerRequestInterface $request): ResponseInterface
    {
        if ($scope->kind() !== ScopeKind::Request) {
            throw new InvalidRequestScopeException(sprintf(
                '%s requires a %s scope; received %s.',
                __METHOD__,
                ScopeKind::Request->value,
                $scope->kind()->value,
            ));
        }

        if (!$scope->hasScopedBinding(ServerRequestInterface::class)) {
            throw new InvalidRequestScopeException(sprintf(
                '%s requires %s to be bound locally in the request scope.',
                __METHOD__,
                ServerRequestInterface::class,
            ));
        }

        $scopeRequest = $scope->get(ServerRequestInterface::class);
        if (!$scopeRequest instanceof ServerRequestInterface || $scopeRequest !== $request) {
            throw new InvalidRequestScopeException(sprintf(
                '%s requires the request scope binding for %s to be the same object as the request argument.',
                __METHOD__,
                ServerRequestInterface::class,
            ));
        }

        return $this->runWithContainer($scope, $request);
    }

    private function runWithContainer(ContainerInterface $runtimeContainer, ServerRequestInterface $request): ResponseInterface
    {

        /** @var Benchmark $benchmark */
        $benchmark = $runtimeContainer->get(Benchmark::class);
        $run = $benchmark->currentOrStart();
        $run->mark('request_received');

        $stack = $runtimeContainer->get(MiddlewareStack::class);
        $this->applyPendingMiddlewareConfiguration($stack);
        $middleware = $runtimeContainer
            ->get(MiddlewareResolver::class)
            ->resolve($stack->all());

        $pipeline = MiddlewarePipeline::create(
            $middleware,
            $runtimeContainer->get(DispatchRequestHandler::class),
        );

        $run->mark('middleware_resolved');
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
