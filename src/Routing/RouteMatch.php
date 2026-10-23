<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;

/**
 * Carries the immutable route identity and extracted parameters selected by the canonical Router.
 *
 * Manually constructed matches may omit method and path metadata for compatibility, while Router
 * matches always expose both values for downstream routing and authorization boundaries.
 */
final readonly class RouteMatch
{
    /**
     * Captures the selected controller, route middleware, parameters and canonical route identity.
     *
     * @param array<string, string> $params
     * @param array<int, class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        private ControllerAction $controllerAction,
        private array $params = [],
        private array $middleware = [],
        private ?string $name = null,
        private ?string $method = null,
        private ?string $path = null,
    ) {
    }

    /**
     * Returns the controller class selected by the matched route.
     */
    public function controller(): string
    {
        return $this->controllerAction->controllerClass();
    }

    /**
     * Returns the public controller method selected by the matched route.
     */
    public function action(): string
    {
        return $this->controllerAction->method();
    }

    /**
     * Returns the immutable controller target used by dispatch.
     */
    public function controllerAction(): ControllerAction
    {
        return $this->controllerAction;
    }

    /**
     * Returns the globally unique route name, or null for an unnamed route.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Returns the canonical method of the matched route when the match originated from Router.
     */
    public function method(): ?string
    {
        return $this->method;
    }

    /**
     * Returns the canonical route template selected by Router, including placeholders.
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * Returns decoded route parameters indexed by placeholder name.
     *
     * @return array<string, string>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * Returns route-specific middleware in execution order.
     *
     * @return array<int, class-string<MiddlewareInterface>>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }
}
