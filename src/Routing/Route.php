<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

use Psr\Http\Server\MiddlewareInterface;

final class Route
{
    /**
     * @param array<int, class-string<MiddlewareInterface>> $middleware
     * @param array<string, list<string>> $parameterConstraints
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $controller,
        private readonly string $action,
        private array $middleware = [],
        private array $parameterConstraints = [],
        private ?string $name = null,
    ) {}

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function controller(): string
    {
        return $this->controller;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function name(?string $name = null): self|string|null
    {
        if ($name === null) {
            return $this->name;
        }

        $this->name = $name;

        return $this;
    }

    /**
     * @param class-string<MiddlewareInterface> ...$middleware
     */
    public function middleware(string ...$middleware): self
    {
        foreach ($middleware as $item) {
            $this->middleware[] = $item;
        }

        return $this;
    }

    /**
     * @return array<int, class-string<MiddlewareInterface>>
     */
    public function middlewareStack(): array
    {
        return $this->middleware;
    }

    /**
     * @param list<string> $allowedValues
     */
    public function constrainParameter(string $name, array $allowedValues): self
    {
        $normalized = [];

        foreach ($allowedValues as $value) {
            $item = trim($value);

            if ($item === '' || in_array($item, $normalized, true)) {
                continue;
            }

            $normalized[] = $item;
        }

        $this->parameterConstraints[$name] = $normalized;

        return $this;
    }

    /**
     * @return array<string, list<string>>
     */
    public function parameterConstraints(): array
    {
        return $this->parameterConstraints;
    }
}
