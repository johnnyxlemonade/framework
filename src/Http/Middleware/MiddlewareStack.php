<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

/**
 * Maintains the ordered middleware class list used to build the global HTTP pipeline.
 */
final class MiddlewareStack
{
    /**
     * Initializes a mutable stack from middleware class names in execution order.
     *
     * @param list<class-string> $middlewares
     */
    public function __construct(
        private array $middlewares = [],
    ) {
    }

    /**
     * Appends middleware to the end of the execution order.
     *
     * @param class-string $middleware
     */
    public function add(string $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Prepends middleware to the beginning of the execution order.
     *
     * @param class-string $middleware
     */
    public function prepend(string $middleware): self
    {
        array_unshift($this->middlewares, $middleware);

        return $this;
    }

    /**
     * Inserts middleware immediately before an existing target.
     *
     * @param class-string $target
     * @param class-string $middleware
     *
     * @throws \InvalidArgumentException When the target is absent
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
     * Inserts middleware immediately after an existing target.
     *
     * @param class-string $target
     * @param class-string $middleware
     *
     * @throws \InvalidArgumentException When the target is absent
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
     * Removes every occurrence of a middleware class while preserving remaining order.
     *
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
     * Returns middleware class names in their current execution order.
     *
     * @return list<class-string>
     */
    public function all(): array
    {
        return array_values($this->middlewares);
    }
}
