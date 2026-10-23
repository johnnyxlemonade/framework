<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Container\ScopeFactoryInterface;
use Lemonade\Framework\Container\ScopeKind;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Diagnostics\ExceptionLogger;
use Lemonade\Framework\Core\Diagnostics\PhpDiagnostics;
use Lemonade\Framework\Core\Health\FrameworkHealthFastPath;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Http\Request\Exception\InvalidRequestMetadataException;
use Lemonade\Framework\Http\Request\RequestContextResolver;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
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
 * request pipeline. Invalid request metadata is rejected with a safe 400 response;
 * other unhandled exceptions are converted to PSR-7 error responses, while
 * {@see handle()} also emits the resulting response to the output.
 */
final class Kernel
{
    private bool $booted = false;
    private readonly ApplicationBootstrapper $bootstrapper;

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
        private readonly FrameworkHealthFastPath $healthFastPath,
        private readonly Benchmark $benchmark,
    ) {
        $this->bootstrapper = new ApplicationBootstrapper(
            $this->context,
            $this->container,
            $this->framework,
            $this->benchmark,
        );
    }

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

        $this->bootstrapper->loadConfiguration(BootstrapEntrypoint::Http);
        $this->container->get(RequestContextResolver::class);
        $this->bootstrapper->bootstrap(BootstrapEntrypoint::Http);

        $this->booted = true;
    }

    /**
     * Boots the kernel and runs the request through the framework runtime.
     *
     * When no request is provided, the kernel creates one from global PHP state.
     * It then creates a {@see ScopeKind::Request} scope, binds the exact request
     * only in that scope, resolves and binds its trusted request context, and delegates
     * dispatch to {@see Framework::runInScope()}.
     * The scope is closed in a finally block. Route-not-found failures are converted
     * to a 404 text response and all other throwables are converted to a 500 text
     * response. Captured exceptions are recorded in the benchmark and logged, and
     * debug mode may include exception details in the response body.
     *
     * The method does not propagate request failures because it converts them to
     * responses. Invalid HTTP metadata always produces a generic 400 response,
     * including in debug mode.
     */
    public function run(?ServerRequestInterface $request = null): ResponseInterface
    {
        return $this->runLifecycle($request, 'kernel.run')['response'];
    }

    /**
     * Handles an incoming HTTP request and emits the resulting response.
     *
     * The method executes the same isolated benchmark and request-scope lifecycle
     * as {@see run()} before passing the response to the configured emitter.
     */
    public function handle(?ServerRequestInterface $request = null): void
    {
        $result = $this->runLifecycle($request, 'kernel.handle');

        $this->emitter->emit(
            $result['response'],
            $result['request'],
        );
    }

    /**
     * @return array{response: ResponseInterface, request: ServerRequestInterface}
     */
    private function runLifecycle(?ServerRequestInterface $request, string $startedAt): array
    {
        /** @var PhpDiagnostics $phpDiagnostics */
        $phpDiagnostics = $this->container->get(PhpDiagnostics::class);
        $phpDiagnostics->install();
        $this->benchmark->start([
            'entrypoint' => 'http',
            'started_at' => $startedAt,
        ])->mark('kernel_start');

        $request ??= $this->container
            ->get(ServerRequestFactory::class)
            ->fromGlobals();

        try {
            $this->bootstrapper->loadConfiguration(BootstrapEntrypoint::Http);
            $requestContext = $this->container
                ->get(RequestContextResolver::class)
                ->resolve($request);
            $scope = $this->beginRequestScope();
            $scope->bindScopedInstance(ServerRequestInterface::class, $request);
            $scope->bindScopedInstance(ResolvedRequestContext::class, $requestContext);

            try {
                $response = $this->healthFastPath->tryHandle(
                    $request,
                );

                if ($response instanceof ResponseInterface) {
                    return [
                        'response' => $response,
                        'request' => $request,
                    ];
                }

                $this->bootstrap();

                return [
                    'response' => $this->framework->runInScope($scope, $request),
                    'request' => $request,
                ];
            } finally {
                $scope->close();
            }
        } catch (InvalidRequestMetadataException $exception) {
            $this->recordBenchmarkException($exception);

            return [
                'response' => $this->badRequestResponse(),
                'request' => $request,
            ];
        } catch (RouteNotFoundException $exception) {
            $this->recordBenchmarkException($exception);
            $this->logException($exception);

            return [
                'response' => $this->notFoundResponse($exception),
                'request' => $request,
            ];
        } catch (Throwable $exception) {
            $this->recordBenchmarkException($exception);
            $this->logException($exception);

            return [
                'response' => $this->errorResponse($exception),
                'request' => $request,
            ];
        } finally {
            $this->benchmark->end();
            $phpDiagnostics->uninstall();
        }
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

    private function beginRequestScope(): ScopedContainerInterface
    {
        if (!$this->container instanceof ScopeFactoryInterface) {
            throw new \LogicException(sprintf(
                'HTTP kernel container must implement %s to create request scopes.',
                ScopeFactoryInterface::class,
            ));
        }

        return $this->container->beginScope(ScopeKind::Request);
    }

    private function recordBenchmarkException(Throwable $exception): void
    {
        $run = $this->benchmark->current();
        if ($run === null || $run->isStopped()) {
            return;
        }

        $run->with('exception', $exception::class);
        $run->mark('kernel_exception');
        $run->stop();
    }

    private function notFoundResponse(RouteNotFoundException $exception): ResponseInterface
    {
        if ($this->context->isDevelopment()) {
            return $this->textResponse(
                statusCode: HttpStatus::NOT_FOUND->value,
                body: '404 Not Found' . PHP_EOL . $exception->getMessage(),
            );
        }

        return $this->textResponse(
            statusCode: HttpStatus::NOT_FOUND->value,
            body: '404 Not Found',
        );
    }

    private function badRequestResponse(): ResponseInterface
    {
        return $this->textResponse(
            statusCode: HttpStatus::BAD_REQUEST->value,
            body: '400 Bad Request',
        );
    }

    private function errorResponse(Throwable $exception): ResponseInterface
    {
        if ($this->context->isDevelopment()) {
            return $this->textResponse(
                statusCode: HttpStatus::INTERNAL_SERVER_ERROR->value,
                body: sprintf(
                    "500 Internal Server Error\n\n%s: %s\n\n%s",
                    $exception::class,
                    $exception->getMessage(),
                    $exception->getTraceAsString(),
                ),
            );
        }

        return $this->textResponse(
            statusCode: HttpStatus::INTERNAL_SERVER_ERROR->value,
            body: '500 Internal Server Error',
        );
    }

    private function textResponse(int $statusCode, string $body): ResponseInterface
    {
        $responseFactory = $this->container->get(Psr17Factory::class);

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
