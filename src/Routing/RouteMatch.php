<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;

final class RouteMatch
{
    /**
     * @param array<string, string> $params
     * @param array<int, class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        private readonly string $controller,
        private readonly string $action,
        private readonly array $params = [],
        private readonly array $middleware = [],
    ) {}

    public function controller(): string
    {
        return $this->controller;
    }

    public function action(): string
    {
        return $this->action;
    }

    /**
     * @return array<string, string>
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * @return array<int, class-string<MiddlewareInterface>>
     */
    public function middleware(): array
    {
        return $this->middleware;
    }
}
