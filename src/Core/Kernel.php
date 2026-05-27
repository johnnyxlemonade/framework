<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Http\HttpServiceProvider;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class Kernel
{
    use KernelBootstrapTrait;

    private const CONFIG_MANIFEST = 'Config.php';

    /**
     * @var list<string>
     */
    private const DEFAULT_CONFIG_FILES = [
        'App.php',
        'Localization.php',
        'Cache.php',
        'Logging.php',
        'Session.php',
        'Database.php',
        'Breadcrumbs.php',
        'Upload.php',
        'Providers.php',
    ];

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

        $this->loadConfiguredConfigFiles();
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

        $this->emitter->emit(
            $this->run($request),
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

    private function loadConfiguredConfigFiles(): void
    {
        foreach ($this->configuredConfigFiles() as $file) {
            $this->framework->configFromFile(
                $this->context->configPath($file),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function configuredConfigFiles(): array
    {
        $manifestPath = $this->context->configPath(self::CONFIG_MANIFEST);

        if (!is_file($manifestPath)) {
            return self::DEFAULT_CONFIG_FILES;
        }

        $manifest = require $manifestPath;

        if (!is_array($manifest)) {
            throw new \LogicException(sprintf(
                'Config manifest "%s" must return an array.',
                self::CONFIG_MANIFEST,
            ));
        }

        $files = $manifest['files'] ?? null;

        if (!is_array($files)) {
            throw new \LogicException(sprintf(
                'Config manifest "%s" must contain array key "files".',
                self::CONFIG_MANIFEST,
            ));
        }

        $normalized = [];

        foreach ($files as $file) {
            if (!is_string($file) || trim($file) === '') {
                throw new \LogicException(sprintf(
                    'Config manifest "%s" contains invalid file name.',
                    self::CONFIG_MANIFEST,
                ));
            }

            $normalized[] = trim($file);
        }

        return $normalized;
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
        $responseFactory = $this->container->has(Psr17Factory::class)
            ? $this->container->get(Psr17Factory::class)
            : new Psr17Factory();

        $response = $responseFactory
            ->createResponse($statusCode)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');

        $response->getBody()->write($body);

        return $response;
    }

    private function logException(Throwable $exception): void
    {
        $this->container
            ->get(ExceptionLogger::class)
            ->log($exception, 'kernel');
    }

}
