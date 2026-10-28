<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Observability\Http\HttpCheckpoint;
use Lemonade\Framework\Observability\Http\Internal\HttpInstrumentation;
use Lemonade\Framework\Routing\RouteMatch;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Reuses a trusted pre-match or matches the request, publishes its RouteMatch, and delegates to route middleware.
 *
 * It honors a dispatch-only path attribute while keeping the original request URI
 * available to the selected controller and its middleware.
 */
final readonly class DispatchRequestHandler implements RequestHandlerInterface
{
    /**
     * Initializes the routing dispatch boundary for one application runtime.
     */
    public function __construct(
        private Router $router,
        private ControllerResolver $resolver,
        private MiddlewareResolver $middlewareResolver,
        private ?HttpInstrumentation $diagnostics = null,
        private ?ScopedContainerInterface $scope = null,
    ) {
    }

    /**
     * Dispatches the canonical match while binding the matched request in its scope.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->diagnostics?->checkpoint(HttpCheckpoint::RouteMatchStarted);
        $dispatchPath = $request->getAttribute(RouteRequestAttributes::DISPATCH_PATH);
        $match = $request->getAttribute(RouteRequestAttributes::MATCH);
        if (!$match instanceof RouteMatch) {
            $match = $this->router->match($request, is_string($dispatchPath) ? $dispatchPath : null);
        }
        $this->diagnostics?->checkpoint(HttpCheckpoint::RouteMatched);

        $request = $request->withAttribute(RouteRequestAttributes::MATCH, $match);
        $this->scope?->bindScopedInstance(ServerRequestInterface::class, $request);
        $this->diagnostics?->matched($match);

        $handler = new ControllerRequestHandler(
            resolver: $this->resolver,
            match: $match,
        );

        $middleware = $this->middlewareResolver->resolve(array_values($match->middleware()));

        return MiddlewarePipeline::create($middleware, $handler)
            ->handle($request);
    }

}
