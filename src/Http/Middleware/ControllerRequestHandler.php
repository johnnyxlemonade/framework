<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Routing\RouteMatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ControllerRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ControllerResolver $resolver,
        private readonly RouteMatch $match,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolver->handle(
            $this->match,
            $request,
        );
    }
}
