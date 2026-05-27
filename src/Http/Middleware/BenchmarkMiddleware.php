<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkResponseInjector;
use Lemonade\Framework\Observability\Benchmark\BenchmarkRun;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class BenchmarkMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Benchmark $benchmark,
        private readonly Config $config,
        private readonly LogManager $logs,
        private readonly BenchmarkResponseInjector $injector,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $run = $this->benchmark->currentOrStart([
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'host' => $request->getUri()->getHost(),
            'content_type' => $request->getHeaderLine('Content-Type'),
            'accept' => $request->getHeaderLine('Accept'),
            'query_keys' => array_map('strval', array_keys($request->getQueryParams())),
            'query_count' => count($request->getQueryParams()),
        ]);

        $route = $request->getAttribute('route');
        if (is_string($route) && $route !== '') {
            $run->with('route', $route);
        }

        $controller = $request->getAttribute('controller');
        if (is_string($controller) && $controller !== '') {
            $run->with('controller', $controller);
        }

        $run->mark('benchmark_middleware_enter');

        try {
            $response = $handler->handle($request);
            $run->with('status', $response->getStatusCode());
            $run->mark('response_ready');
        } catch (Throwable $exception) {
            $run->with('status', 500);
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

    private function logRun(BenchmarkRun $run): void
    {
        if (!(bool) $this->config->get('benchmark.log.enabled', false)) {
            return;
        }

        $data = $run->toArray();
        $context = $data['context'];

        $this->logs->benchmark()->info('request.benchmark', [
            ...$data,
            'status' => $context['status'] ?? null,
            'exception_class' => $context['exception_class'] ?? null,
            'exception_message' => $context['exception_message'] ?? null,
            'elapsed_ms' => round($run->elapsedMs(), 3),
            'memory_delta_bytes' => $run->memoryDeltaBytes(),
            'peak_memory_bytes' => $run->peakMemoryBytes(),
            'marks' => $run->marks(),
        ]);
    }
}
