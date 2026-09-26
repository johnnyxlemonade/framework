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

final class DispatchRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly ControllerResolver $resolver,
        private readonly MiddlewareResolver $middlewareResolver,
        private readonly Benchmark $benchmark,
        private readonly ?ScopedContainerInterface $scope = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->markBenchmark('route_match_start');
        $match = $this->router->match($request);
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

    private function markBenchmark(string $name): void
    {
        $run = $this->benchmark->current();
        if ($run === null) {
            return;
        }

        $run->mark($name);
    }

    private function captureBenchmarkRouteMetadata(ServerRequestInterface $request): void
    {
        $run = $this->benchmark->current();
        if ($run === null) {
            return;
        }

        BenchmarkMiddleware::captureRouteMetadata($run, $request);
    }
}
