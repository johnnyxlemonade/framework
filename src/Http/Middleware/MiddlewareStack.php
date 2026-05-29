<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

final class MiddlewareStack
{
    /**
     * @param list<class-string> $middlewares
     */
    public function __construct(
        private array $middlewares = [],
    ) {}

    /**
     * @param class-string $middleware
     */
    public function add(string $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * @param class-string $middleware
     */
    public function prepend(string $middleware): self
    {
        array_unshift($this->middlewares, $middleware);

        return $this;
    }

    /**
     * @param class-string $target
     * @param class-string $middleware
     */
    public function insertBefore(string $target, string $middleware): self
    {
        $index = array_search($target, $this->middlewares, true);
        if ($index === false) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot insert middleware "%s" before "%s": target not found.',
                $middleware,
                $target,
            ));
        }

        array_splice($this->middlewares, $index, 0, [$middleware]);

        return $this;
    }

    /**
     * @param class-string $target
     * @param class-string $middleware
     */
    public function insertAfter(string $target, string $middleware): self
    {
        $index = array_search($target, $this->middlewares, true);
        if ($index === false) {
            throw new \InvalidArgumentException(sprintf(
                'Cannot insert middleware "%s" after "%s": target not found.',
                $middleware,
                $target,
            ));
        }

        array_splice($this->middlewares, $index + 1, 0, [$middleware]);

        return $this;
    }

    /**
     * @param class-string $middleware
     */
    public function remove(string $middleware): self
    {
        $this->middlewares = array_values(array_filter(
            $this->middlewares,
            static fn(string $candidate): bool => $candidate !== $middleware,
        ));

        return $this;
    }

    /**
     * @return list<class-string>
     */
    public function all(): array
    {
        return array_values($this->middlewares);
    }
}
