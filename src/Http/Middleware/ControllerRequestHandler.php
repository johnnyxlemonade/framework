<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Core\ControllerResolver;
use Lemonade\Framework\Routing\RouteMatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminates a matched middleware pipeline by invoking its selected controller action.
 */
final readonly class ControllerRequestHandler implements RequestHandlerInterface
{
    /**
     * Binds controller resolution to one immutable route match.
     */
    public function __construct(
        private ControllerResolver $resolver,
        private RouteMatch $match,
    ) {
    }

    /**
     * Delegates the request to the controller selected by the bound route match.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->resolver->handle(
            $this->match,
            $request,
        );
    }
}
