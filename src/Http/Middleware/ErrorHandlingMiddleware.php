<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Http\Error\ErrorPageRenderer;
use Lemonade\Framework\Http\Error\ExceptionResponseResolverInterface;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Http\Request\Exception\InvalidRequestMetadataException;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Translates expected HTTP failures and unexpected throwables into safe responses and error records.
 */
final readonly class ErrorHandlingMiddleware implements MiddlewareInterface
{
    /**
     * Connects response rendering, error logging and an optional integration response resolver.
     */
    public function __construct(
        private Psr17Factory $responseFactory,
        private LogManager $logs,
        private HttpLogContext $httpLogContext,
        private ErrorPageRenderer $errorPageRenderer,
        private ?ExceptionResponseResolverInterface $exceptionResponseResolver = null,
    ) {
    }

    /**
     * Handles the request and maps exceptions without treating ordinary 404 responses as runtime errors.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (InvalidRequestMetadataException) {
            return $this->htmlResponse(
                statusCode: HttpStatus::BAD_REQUEST->value,
                body: '400 Bad Request',
            );
        } catch (RouteNotFoundException|NotFoundHttpException $exception) {
            $this->logException($exception, $request);

            $resolved = $this->resolveExceptionResponse($exception, $request);
            if ($resolved instanceof ResponseInterface) {
                return $resolved;
            }

            return $this->htmlResponse(
                statusCode: HttpStatus::NOT_FOUND->value,
                body: $this->errorPageRenderer->notFound($exception),
            );
        } catch (Throwable $exception) {
            $this->logException($exception, $request);

            $resolved = $this->resolveExceptionResponse($exception, $request);
            if ($resolved instanceof ResponseInterface) {
                return $resolved;
            }

            return $this->htmlResponse(
                statusCode: HttpStatus::INTERNAL_SERVER_ERROR->value,
                body: $this->errorPageRenderer->internalServerError($exception),
            );
        }
    }

    private function htmlResponse(int $statusCode, string $body): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($statusCode)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->responseFactory->createStream($body));
    }

    private function resolveExceptionResponse(
        Throwable $exception,
        ServerRequestInterface $request,
    ): ?ResponseInterface {
        return $this->exceptionResponseResolver?->resolve(
            $exception,
            $request,
        );
    }

    private function logException(Throwable $exception, ServerRequestInterface $request): void
    {
        if (!$this->shouldLogException($exception)) {
            return;
        }

        try {
            if ($exception instanceof RouteNotFoundException || $exception instanceof NotFoundHttpException) {
                return;
            }

            $this->logs->error()->error('Unhandled HTTP request exception.', [
                'exception_class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'request' => $this->httpLogContext->request($request),
            ]);
        } catch (Throwable) {
            // Logging must never break error handling.
        }
    }

    private function shouldLogException(Throwable $exception): bool
    {
        return !($exception instanceof RouteNotFoundException || $exception instanceof NotFoundHttpException);
    }
}
