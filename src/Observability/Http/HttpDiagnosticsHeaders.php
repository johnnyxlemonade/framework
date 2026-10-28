<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http;

use Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfig;
use Psr\Http\Message\ResponseInterface;

/**
 * Exposes a completed summary through headers without reading or replacing the response body.
 */
final readonly class HttpDiagnosticsHeaders
{
    /**
     * Uses only the explicit exposure policy, with no dependency on application environment.
     */
    public function __construct(
        private HttpDiagnosticsConfig $config,
    ) {
    }

    /**
     * Returns the exact response when disabled and changes only four diagnostic headers when enabled.
     *
     * Existing body metadata and stream state are preserved, including for HEAD and streamed responses.
     */
    public function apply(ResponseInterface $response, HttpMetrics $metrics): ResponseInterface
    {
        if (!$this->config->enabled()) {
            return $response;
        }

        return $response
            ->withHeader('X-Lemonade-Time-Ms', number_format($metrics->durationMs(), 3, '.', ''))
            ->withHeader('X-Lemonade-PHP-Peak-Bytes', (string) $metrics->phpPeakMemoryBytes())
            ->withHeader('X-Lemonade-SQL-Count', (string) $metrics->sqlCount())
            ->withHeader('X-Lemonade-SQL-Time-Ms', number_format($metrics->sqlMs(), 3, '.', ''));
    }
}
