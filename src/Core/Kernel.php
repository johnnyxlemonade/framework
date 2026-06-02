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

final class Kernel
{
    use KernelBootstrapTrait;

    private bool $booted = false;

    public function __construct(
        private readonly ApplicationContext $context,
        private readonly ContainerInterface $container,
        private readonly Framework $framework,
        private readonly ResponseEmitter $emitter,
    ) {}

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

    public function framework(): Framework
    {
        return $this->framework;
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

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
