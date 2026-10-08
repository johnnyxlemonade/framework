<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Matches a request, publishes its RouteMatch, and delegates to route middleware.
 *
 * It honors a dispatch-only path attribute while keeping the original request URI
 * available to the selected controller and its middleware.
 */
final class DispatchRequestHandler implements RequestHandlerInterface
{
    /**
     * Initializes the routing dispatch boundary for one application runtime.
     */
    public function __construct(
        private readonly Router $router,
        private readonly ControllerResolver $resolver,
        private readonly MiddlewareResolver $middlewareResolver,
        private readonly Benchmark $benchmark,
        private readonly ?ScopedContainerInterface $scope = null,
    ) {
    }

    /**
     * Matches and dispatches the request while binding the matched request in its scope.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->markBenchmark('route_match_start');
        $dispatchPath = $request->getAttribute(RouteRequestAttributes::DISPATCH_PATH);
        $match = $this->router->match($request, is_string($dispatchPath) ? $dispatchPath : null);
        $this->markBenchmark('route_matched');

        $request = $request->withAttribute(RouteRequestAttributes::MATCH, $match);
        $this->scope?->bindScopedInstance(ServerRequestInterface::class, $request);
        $this->captureBenchmarkRouteMetadata($request);

        $handler = new ControllerRequestHandler(
            resolver: $this->resolver,
            match: $match,
        );

        $middleware = $this->middlewareResolver->resolve(array_values($match->middleware()));

        return MiddlewarePipeline::create($middleware, $handler)
            ->handle($request);
    }

    /**
     * Records a dispatch milestone only when a benchmark run is active.
     */
    private function markBenchmark(string $name): void
    {
        $run = $this->benchmark->current();
        if ($run === null) {
            return;
        }

        $run->mark($name);
    }

    /**
     * Copies matched-route metadata into the active benchmark context when present.
     */
    private function captureBenchmarkRouteMetadata(ServerRequestInterface $request): void
    {
        $run = $this->benchmark->current();
        if ($run === null) {
            return;
        }

        BenchmarkMiddleware::captureRouteMetadata($run, $request);
    }
}
