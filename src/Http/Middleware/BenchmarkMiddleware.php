<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkResponseInjector;
use Lemonade\Framework\Observability\Benchmark\BenchmarkRun;
use Lemonade\Framework\Routing\RouteMatch;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Measures the HTTP lifecycle, records route metadata, and optionally injects benchmark output.
 */
final readonly class BenchmarkMiddleware implements MiddlewareInterface
{
    /**
     * Connects request timing, configured benchmark logging, and response decoration.
     */
    public function __construct(
        private Benchmark $benchmark,
        private LoggingConfig $config,
        private LogManager $logs,
        private BenchmarkResponseInjector $injector,
    ) {
    }

    /**
     * Completes and reports the lifecycle-owned run for successful responses and thrown failures.
     *
     * @throws LogicException When the outer HTTP lifecycle did not start a benchmark run
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $run = $this->benchmark->current();
        if ($run === null || $run->isStopped()) {
            throw new LogicException('BenchmarkMiddleware requires an active HTTP benchmark run.');
        }

        $run->with('method', $request->getMethod());
        $run->with('path', $request->getUri()->getPath());
        $run->with('host', $request->getUri()->getHost());
        $run->with('content_type', $request->getHeaderLine('Content-Type'));
        $run->with('accept', $request->getHeaderLine('Accept'));
        $run->with('query_keys', array_map('strval', array_keys($request->getQueryParams())));
        $run->with('query_count', count($request->getQueryParams()));

        self::captureRouteMetadata($run, $request);

        $run->mark('benchmark_middleware_enter');

        try {
            $response = $handler->handle($request);
            $run->with('status', $response->getStatusCode());
            $run->mark('response_ready');
        } catch (Throwable $exception) {
            $run->with('status', HttpStatus::INTERNAL_SERVER_ERROR->value);
            $run->with('exception_class', $exception::class);
            $run->with('exception_message', $exception->getMessage());
            $run->mark('exception');
            $run->stop();
            $this->logRun($run);

            throw $exception;
        }

        $run->stop();
        $this->logRun($run);

        return $this->injector->inject($response, $run);
    }

    /**
     * Captures metadata from the routing request-attribute contract.
     *
     * @internal Called by dispatch after a route match, because this global middleware runs before routing.
     */
    public static function captureRouteMetadata(BenchmarkRun $run, ServerRequestInterface $request): void
    {
        $match = $request->getAttribute(RouteRequestAttributes::MATCH);
        if (!$match instanceof RouteMatch) {
            return;
        }

        if ($match->name() !== null) {
            $run->with('route', $match->name());
        }

        $run->with('controller', $match->controller());
        $run->with('action', $match->action());
    }

    private function logRun(BenchmarkRun $run): void
    {
        if (!$this->config->benchmarkEnabled) {
            return;
        }

        $data = $run->toArray();
        $context = $data['context'];

        $this->logs->benchmark()->info('request.benchmark', array_replace($data, [
            'status' => $context['status'] ?? null,
            'exception_class' => $context['exception_class'] ?? null,
            'exception_message' => $context['exception_message'] ?? null,
            'elapsed_ms' => round($run->elapsedMs(), 3),
            'memory_delta_bytes' => $run->memoryDeltaBytes(),
            'peak_memory_bytes' => $run->peakMemoryBytes(),
            'marks' => $run->marks(),
        ]));
    }
}
