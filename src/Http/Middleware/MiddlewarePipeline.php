<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Executes an ordered PSR-15 middleware chain before a terminal request handler.
 */
final readonly class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * Creates one immutable cursor within the shared middleware sequence.
     *
     * @param list<MiddlewareInterface> $stack
     *
     * @throws \InvalidArgumentException When the middleware sequence is not a list
     */
    public function __construct(
        private array $stack,
        private RequestHandlerInterface $fallback,
        private int $index = 0,
    ) {
        if (!array_is_list($stack)) {
            throw new \InvalidArgumentException('MiddlewarePipeline stack must be a list.');
        }
    }

    /**
     * Normalizes a middleware sequence and returns its initial execution cursor.
     *
     * @param array<int, MiddlewareInterface> $stack
     */
    public static function create(array $stack, RequestHandlerInterface $fallback): self
    {
        return new self(
            array_values($stack),
            $fallback,
        );
    }

    /**
     * Invokes the current middleware or the terminal handler after the sequence is exhausted.
     */
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
