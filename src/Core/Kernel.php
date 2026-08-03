<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\ConfigLoader;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Http\HttpServiceProvider;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * HTTP application kernel for Lemonade Framework applications.
 *
 * The kernel bootstraps the application, loads configuration, registers core
 * and application service providers, registers routes, and runs the framework
 * request pipeline. Unhandled exceptions are converted to PSR-7 error
 * responses, while {@see handle()} also emits the resulting response to the
 * output.
 */
final class Kernel
{
    use KernelBootstrapTrait;

    private bool $booted = false;

    /**
     * Accepts the runtime services used by the HTTP kernel.
     *
     * Bootstrap is performed lazily through {@see bootstrap()} when the kernel
     * first handles or runs a request.
     */
    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ContainerInterface $container,
        private readonly Framework $framework,
        private readonly ResponseEmitter $emitter,
    ) {}

    /**
     * Bootstraps the HTTP kernel once for the current instance.
     *
     * When the kernel has already been booted, the method returns without doing
     * any further work. Otherwise it loads application configuration files,
     * applies runtime app configuration, prepares core diagnostics and logging,
     * registers shared and HTTP framework providers, registers configured
     * application providers, loads routes from the Routing.php file, and marks
     * the kernel as booted.
     *
     * The method is idempotent for a single kernel instance.
     */
    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        $this->loadApplicationConfigFiles();
        $this->markBenchmark('config_loaded');

        $this->applyRuntimeAppConfig();
        $this->registerCoreProvidersWithDiagnostics();
        $this->markBenchmark('core_logger_ready');

        $this->framework
            ->register(new HttpServiceProvider());
        $this->registerCommonFrameworkProviders();
        $this->markBenchmark('framework_providers_registered');

        $this->registerConfiguredProviders();
        $this->markBenchmark('app_providers_registered');

        $this->framework
            ->routesFromFile($this->context->configPath('Routing.php'));
        $this->markBenchmark('routes_registered');

        $this->booted = true;
    }

    /**
     * Boots the kernel and runs the request through the framework runtime.
     *
     * When no request is provided, request creation is delegated to
     * {@see Framework::run()}. Route-not-found failures are converted to a 404
     * text response and all other throwables are converted to a 500 text
     * response. Captured exceptions are recorded in the benchmark and logged,
     * and debug mode may include exception details in the response body.
     *
     * The method does not propagate exceptions because it converts them to
     * responses.
     */
    public function run(?ServerRequestInterface $request = null): ResponseInterface
    {
        try {
            $this->bootstrap();

            return $this->framework->run($request);
        } catch (RouteNotFoundException $exception) {
            $this->benchmark()?->currentOrStart()->with('exception', $exception::class);
            $this->markBenchmark('kernel_exception');
            $this->benchmark()?->currentOrStart()->stop();
            $this->logException($exception);

            return $this->notFoundResponse($exception);
        } catch (Throwable $exception) {
            $this->benchmark()?->currentOrStart()->with('exception', $exception::class);
            $this->markBenchmark('kernel_exception');
            $this->benchmark()?->currentOrStart()->stop();
            $this->logException($exception);

            return $this->errorResponse($exception);
        }
    }

    /**
     * Handles an incoming HTTP request and emits the resulting response.
     *
     * The method starts the HTTP benchmark, creates a request from PHP globals
     * through the server request factory when none is supplied, runs the kernel,
     * and emits the final response through the configured response emitter.
     *
     * This is the HTTP entrypoint and has the side effect of writing the
     * response to the output.
     */
    public function handle(?ServerRequestInterface $request = null): void
    {
        $this->benchmark()?->currentOrStart([
            'entrypoint' => 'http',
            'started_at' => 'kernel.handle',
        ])->mark('kernel_start');

        $request ??= $this->container
            ->get(ServerRequestFactory::class)
            ->fromGlobals();

        $this->emitter->emit(
            $this->run($request),
            $request,
        );
    }

    /**
     * Returns the framework runtime managed by the kernel.
     */
    public function framework(): Framework
    {
        return $this->framework;
    }

    /**
     * Returns the dependency injection container used by the kernel.
     */
    public function container(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Returns the application context used during kernel bootstrap.
     */
    public function context(): ApplicationContext
    {
        return $this->context;
    }

    private function loadApplicationConfigFiles(): void
    {
        (new ConfigLoader())->loadApplication(
            $this->framework,
            $this->context,
            ConfigLoader::ENTRYPOINT_HTTP,
        );
    }

    private function notFoundResponse(RouteNotFoundException $exception): ResponseInterface
    {
        if ($this->context->debug()) {
            return $this->textResponse(
                statusCode: 404,
                body: '404 Not Found' . PHP_EOL . $exception->getMessage(),
            );
        }

        return $this->textResponse(
            statusCode: 404,
            body: '404 Not Found',
        );
    }

    private function errorResponse(Throwable $exception): ResponseInterface
    {
        if ($this->context->debug()) {
            return $this->textResponse(
                statusCode: 500,
                body: sprintf(
                    "500 Internal Server Error\n\n%s: %s\n\n%s",
                    $exception::class,
                    $exception->getMessage(),
                    $exception->getTraceAsString(),
                ),
            );
        }

        return $this->textResponse(
            statusCode: 500,
            body: '500 Internal Server Error',
        );
    }

    private function textResponse(int $statusCode, string $body): ResponseInterface
    {
        $responseFactory = $this->container->isBound(Psr17Factory::class)
            ? $this->container->get(Psr17Factory::class)
            : new Psr17Factory();

        return $responseFactory
            ->createResponse($statusCode)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withBody($responseFactory->createStream($body));
    }

    private function logException(Throwable $exception): void
    {
        $this->container
            ->get(ExceptionLogger::class)
            ->log($exception, 'kernel');
    }

}
