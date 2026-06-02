<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DispatchRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly ControllerResolver $resolver,
        private readonly MiddlewareResolver $middlewareResolver,
        private readonly ContainerInterface $container,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->markBenchmark('route_match_start');
        $match = $this->router->match($request);
        $this->markBenchmark('route_matched');

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
        if (!$this->container->isBound(Benchmark::class)) {
            return;
        }

        $benchmark = $this->container->get(Benchmark::class);
        $run = $benchmark->current();
        if ($run === null) {
            return;
        }

        $run->mark($name);
    }
}
