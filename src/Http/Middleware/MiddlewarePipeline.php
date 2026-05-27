<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @param array<int, MiddlewareInterface> $stack
     */
    public function __construct(
        private readonly array $stack,
        private readonly RequestHandlerInterface $fallback,
        private readonly int $index = 0,
    ) {}

    /**
     * @param array<int, MiddlewareInterface> $stack
     */
    public static function create(array $stack, RequestHandlerInterface $fallback): self
    {
        return new self(
            array_values($stack),
            $fallback,
        );
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->stack[$this->index])) {
            return $this->fallback->handle($request);
        }

        $middleware = $this->stack[$this->index];

        return $middleware->process(
            $request,
            new self($this->stack, $this->fallback, $this->index + 1),
        );
    }
}
