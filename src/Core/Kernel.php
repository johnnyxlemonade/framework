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
use Lemonade\Framework\Http\Request\Exception\InvalidRequestMetadataException;
use Lemonade\Framework\Http\Request\RequestContextResolver;
use Lemonade\Framework\Http\Request\ResolvedRequestContext;
use Lemonade\Framework\Observability\Http\Internal\HttpInstrumentation;
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
        private readonly HttpInstrumentation $diagnostics,
    ) {
        $this->bootstrapper = new ApplicationBootstrapper(
            $this->context,
            $this->container,
            $this->framework,
            $this->diagnostics,
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
     * response. Non-routing failures are logged through the sanitized HTTP boundary. Debug mode may
     * include exception details in the response body.
     *
     * The method does not propagate request failures because it converts them to
     * responses. Invalid HTTP metadata always produces a generic 400 response,
     * including in debug mode.
     */
    public function run(?ServerRequestInterface $request = null): ResponseInterface
    {
        return $this->runLifecycle($request);
    }

    /**
     * Handles an incoming HTTP request and emits the resulting response.
     *
     * The method executes the same isolated measurement and request-scope lifecycle
     * as {@see run()} before passing the response to the configured emitter.
     */
    public function handle(?ServerRequestInterface $request = null): void
    {
        $response = $this->runLifecycle($request);
        $this->emitter->emit($response, $request);
    }

    private function runLifecycle(?ServerRequestInterface &$request): ResponseInterface
    {
        $phpDiagnostics = $this->container->get(PhpDiagnostics::class);
        $phpDiagnostics->install(sanitizeMessages: true);

        try {
            return $this->diagnostics->handle(
                $request,
                fn() => $this->bootstrapper->loadConfiguration(BootstrapEntrypoint::Http),
                function (ServerRequestInterface $current): ResponseInterface {
                    $requestContext = $this->container->get(RequestContextResolver::class)->resolve($current);
                    $scope = $this->beginRequestScope();

                    try {
                        $scope->bindScopedInstance(ServerRequestInterface::class, $current);
                        $scope->bindScopedInstance(ResolvedRequestContext::class, $requestContext);
                        $response = $this->healthFastPath->tryHandle($current);
                        if ($response !== null) {
                            return $response;
                        }

                        $this->bootstrap();

                        return $this->framework->runInScope($scope, $current);
                    } finally {
                        $scope->close();
                    }
                },
                fn(Throwable $exception): ResponseInterface => $this->failureResponse($exception),
            );
        } finally {
            $phpDiagnostics->uninstall();
        }
    }

    private function failureResponse(Throwable $exception): ResponseInterface
    {
        if ($exception instanceof InvalidRequestMetadataException) {
            return $this->badRequestResponse();
        }
        if ($exception instanceof RouteNotFoundException) {
            return $this->notFoundResponse($exception);
        }

        $this->logException($exception);

        return $this->errorResponse($exception);
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
            ->logHttp($exception, 'kernel');
    }
}
