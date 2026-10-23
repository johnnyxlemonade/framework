<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
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
     * @var list<string>
     */
    private const array SAFE_MARK_NAMES = [
        'start',
        'kernel_start',
        'framework_start',
        'config_loaded',
        'bootstrap_start',
        'core_providers_registered',
        'framework_config_runtime_resolved',
        'app_provider_config_resolved',
        'http_provider_registered',
        'common_provider_registration_finished',
        'app_providers_registered',
        'providers_booted',
        'routes_registered',
        'request_received',
        'middleware_resolved',
        'middleware_enter',
        'benchmark_middleware_enter',
        'route_match_start',
        'route_matched',
        'controller_resolve_start',
        'controller_resolved',
        'controller_action_start',
        'controller_action_finished',
        'response_created',
        'response_ready',
        'exception',
        'finish',
    ];

    /**
     * Connects request timing, configured benchmark logging, and response decoration.
     *
     * The optional context dependency preserves direct construction compatibility while
     * applying the same safe metadata policy as request and error logging.
     */
    public function __construct(
        private Benchmark $benchmark,
        private LoggingConfig $config,
        private LogManager $logs,
        private BenchmarkResponseInjector $injector,
        private ?HttpLogContext $httpLogContext = null,
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

        $httpContext = $this->safeHttpLogContext()->request($request);

        foreach ($httpContext as $key => $value) {
            $run->with($key, $value);
        }

        self::captureRouteMetadata($run, $request);

        $run->mark('benchmark_middleware_enter');

        try {
            $response = $handler->handle($request);
            $status = $response->getStatusCode();
            $run->with('status', $status);
            $run->mark('response_ready');
        } catch (Throwable $exception) {
            $status = HttpStatus::INTERNAL_SERVER_ERROR->value;
            $run->with('status', $status);
            $run->with('exception_class', $exception::class);
            $run->mark('exception');
            $run->stop();
            $this->logRun(
                run: $run,
                httpContext: $httpContext,
                status: $status,
                exceptionClass: $exception::class,
            );

            throw $exception;
        }

        $run->stop();
        $this->logRun(
            run: $run,
            httpContext: $httpContext,
            status: $status,
        );

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

    /**
     * @param array{method:string,path:string,request_id:string|null} $httpContext
     */
    private function logRun(
        BenchmarkRun $run,
        array $httpContext,
        int $status,
        ?string $exceptionClass = null,
    ): void {
        if (!$this->config->benchmarkEnabled) {
            return;
        }

        $data = $run->toArray();
        $database = $run->database();
        $durationMs = round($run->elapsedMs(), 3);
        $context = [
            ...$httpContext,
            'status' => $status,
            'exception_class' => $exceptionClass,
        ];

        $this->logs->benchmark()->info('request.benchmark', [
            'context' => $context,
            'elapsed_ms' => $durationMs,
            'memory_start_bytes' => $data['memory_start_bytes'],
            'memory_end_bytes' => $data['memory_end_bytes'],
            'memory_delta_bytes' => $data['memory_delta_bytes'],
            'allocated_memory_start_bytes' => $data['allocated_memory_start_bytes'],
            'allocated_memory_end_bytes' => $data['allocated_memory_end_bytes'],
            'allocated_memory_delta_bytes' => $data['allocated_memory_delta_bytes'],
            'peak_memory_bytes' => $data['peak_memory_bytes'],
            'peak_memory_delta_bytes' => $data['peak_memory_delta_bytes'],
            'peak_allocated_memory_bytes' => $data['peak_allocated_memory_bytes'],
            'peak_allocated_memory_delta_bytes' => $data['peak_allocated_memory_delta_bytes'],
            'database' => [
                'connection_ms' => $database['connection_ms'],
                'query_ms' => $database['query_ms'],
                'query_count' => $database['query_count'],
            ],
            'marks' => array_values(array_filter(
                $run->marks(),
                static fn(array $mark): bool => in_array($mark['name'], self::SAFE_MARK_NAMES, true),
            )),
            'status' => $status,
            'exception_class' => $exceptionClass,
            'duration_ms' => $durationMs,
        ]);
    }

    private function safeHttpLogContext(): HttpLogContext
    {
        return $this->httpLogContext ?? new HttpLogContext(new HttpRequestInspector());
    }
}
