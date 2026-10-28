<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http;

use Lemonade\Framework\Routing\RouteMatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Receives optional profiling data without owning the framework's HTTP measurements.
 *
 * The integration owner must isolate observer failures from request handling and release
 * the observer on every exit. This contract alone does not activate instrumentation.
 */
interface HttpDiagnosticsObserverInterface
{
    /**
     * Receives an actual boundary on the same monotonic origin as the final duration.
     *
     * RequestStarted at zero begins a fresh capture. Call only when observed; do not
     * synthesize completion checkpoints for skipped or failed operations.
     */
    public function checkpoint(HttpCheckpoint $checkpoint, float $elapsedMs): void;

    /**
     * Receives the authoritative completed summary; implementations must not recompute its totals.
     */
    public function completed(HttpMetrics $metrics): void;

    /**
     * Publishes the completed capture and optionally decorates the response after measurement ends.
     *
     * The caller preserves the original response if publishing fails. The matched route is
     * captured before request-scope cleanup; no closed scope needs to be resolved here.
     */
    public function response(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?RouteMatch $match,
    ): ResponseInterface;
}
