<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Http\Error\ErrorPageRenderer;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class ErrorHandlingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly Psr17Factory $responseFactory,
        private readonly LogManager $logs,
        private readonly HttpLogContext $httpLogContext,
        private readonly ErrorPageRenderer $errorPageRenderer,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (RouteNotFoundException|NotFoundHttpException $exception) {
            $this->logException($exception, $request);

            return $this->htmlResponse(
                statusCode: 404,
                body: $this->errorPageRenderer->notFound($exception),
            );
        } catch (Throwable $exception) {
            $this->logException($exception, $request);

            return $this->htmlResponse(
                statusCode: 500,
                body: $this->errorPageRenderer->internalServerError($exception),
            );
        }
    }

    private function htmlResponse(int $statusCode, string $body): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($statusCode)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        $response->getBody()->write($body);

        return $response;
    }

    private function logException(Throwable $exception, ServerRequestInterface $request): void
    {
        if (!$this->shouldLogException($exception)) {
            return;
        }

        try {
            $this->logs->error()->error($exception->getMessage(), [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'request' => $this->httpLogContext->request($request),
            ]);
        } catch (Throwable) {
            // Logging must never break error handling.
        }
    }

    private function shouldLogException(Throwable $exception): bool
    {
        if (!$this->logs->enabled('error', true)) {
            return false;
        }

        if ($exception instanceof RouteNotFoundException || $exception instanceof NotFoundHttpException) {
            return (bool) $this->config->get('error.log.not_found', false);
        }

        return true;
    }
}
