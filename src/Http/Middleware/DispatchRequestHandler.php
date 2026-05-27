<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DispatchRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly ControllerResolver $resolver,
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

        $middleware = [];

        foreach ($match->middleware() as $middlewareClass) {
            $resolved = $this->container->get($middlewareClass);

            if (!$resolved instanceof MiddlewareInterface) {
                throw new \RuntimeException(sprintf(
                    'Target class "%s" is not a valid middleware.',
                    $middlewareClass,
                ));
            }

            $middleware[] = $resolved;
        }

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
