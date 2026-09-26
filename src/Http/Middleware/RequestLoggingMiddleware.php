<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestLoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggingConfig $config,
        private readonly LogManager $logs,
        private readonly HttpLogContext $context,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->logs->enabled('request', false)) {
            return $handler->handle($request);
        }

        $startedAt = microtime(true);

        $response = $handler->handle($request);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (!$this->shouldLogResponse($response)) {
            return $response;
        }

        $this->logRequest($request, $response, $durationMs);

        return $response;
    }

    private function logRequest(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $durationMs,
    ): void {
        $this->logs->request()->log(
            $this->levelForStatus($response->getStatusCode()),
            'http.request',
            $this->context->requestResponse($request, $response, $durationMs),
        );
    }

    private function levelForStatus(int $statusCode): string
    {
        if ($statusCode >= HttpStatus::INTERNAL_SERVER_ERROR->value) {
            return 'error';
        }

        if ($statusCode >= HttpStatus::BAD_REQUEST->value) {
            return 'warning';
        }

        if ($statusCode >= 300) {
            return 'notice';
        }

        return 'info';
    }

    private function shouldLogResponse(ResponseInterface $response): bool
    {
        $minStatus = $this->config->requestMinStatus;

        if ($minStatus <= 0) {
            return true;
        }

        return $response->getStatusCode() >= $minStatus;
    }
}
