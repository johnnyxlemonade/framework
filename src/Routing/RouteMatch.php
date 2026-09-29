<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;

final readonly class RouteMatch
{
    /**
     * @param array<string, string> $params
     * @param array<int, class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        private ControllerAction $controllerAction,
        private array $params = [],
        private array $middleware = [],
        private ?string $name = null,
    ) {}

    public function controller(): string
    {
        return $this->controllerAction->controllerClass();
    }

    public function action(): string
    {
        return $this->controllerAction->method();
    }

    public function controllerAction(): ControllerAction
    {
        return $this->controllerAction;
    }

    public function name(): ?string
    {
        return $this->name;
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
