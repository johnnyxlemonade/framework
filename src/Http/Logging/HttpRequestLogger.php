<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Logging;

use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Observability\Http\HttpMetrics;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Records completed HTTP exchanges that satisfy the configured request-log threshold.
 */
final readonly class HttpRequestLogger
{
    /**
     * Connects logging policy, channel selection, and structured HTTP context generation.
     */
    public function __construct(
        private LoggingConfig $config,
        private LogManager $logs,
        private HttpLogContext $context,
    ) {
    }

    /**
     * Logs an eligible completed response using the common HTTP duration, rounded to integer milliseconds.
     */
    public function log(ServerRequestInterface $request, ResponseInterface $response, HttpMetrics $metrics): void
    {
        if (!$this->logs->enabled('request', false) || !$this->shouldLogResponse($response)) {
            return;
        }

        $this->logRequest($request, $response, (int) round($metrics->durationMs()));
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
        $minStatus = $this->config->requestMinStatus();

        if ($minStatus <= 0) {
            return true;
        }

        return $response->getStatusCode() >= $minStatus;
    }
}
