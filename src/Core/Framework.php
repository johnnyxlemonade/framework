<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Http\Middleware\BenchmarkMiddleware;
use Lemonade\Framework\Http\Middleware\DispatchRequestHandler;
use Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware;
use Lemonade\Framework\Http\Middleware\HtmlMinifyMiddleware;
use Lemonade\Framework\Http\Middleware\MiddlewarePipeline;
use Lemonade\Framework\Http\Middleware\PoweredByMiddleware;
use Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware;
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

    public function run(?ServerRequestInterface $request = null): ResponseInterface
    {
        $request ??= $this->container
            ->get(ServerRequestFactory::class)
            ->fromGlobals();

        /** @var Benchmark $benchmark */
        $benchmark = $this->container->get(Benchmark::class);
        $run = $benchmark->currentOrStart();
        $run->mark('request_received');

        $pipeline = MiddlewarePipeline::create(
            [
                $this->container->get(RequestLoggingMiddleware::class),
                $this->container->get(BenchmarkMiddleware::class),
                $this->container->get(ErrorHandlingMiddleware::class),
                $this->container->get(PoweredByMiddleware::class),
                $this->container->get(HtmlMinifyMiddleware::class),
            ],
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
}
