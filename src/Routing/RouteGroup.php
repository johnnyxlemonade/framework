<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;

final class RouteGroup
{
    /**
     * @param array<int, Route> $routes
     */
    public function __construct(
        private readonly array $routes,
    ) {}

    /**
     * @param class-string<MiddlewareInterface> ...$middleware
     */
    public function middleware(string ...$middleware): self
    {
        foreach ($this->routes as $route) {
            $route->middleware(...$middleware);
        }

        return $this;
    }

    /**
     * @return array<int, Route>
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
