<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class OptionsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'OPTIONS') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();

        if ($this->router->hasExplicitRouteForPath('OPTIONS', $path)) {
            return $handler->handle($request);
        }

        $allowedMethods = $this->router->allowedMethodsForPath($path);
        if ($allowedMethods === []) {
            return $handler->handle($request);
        }

        return $this->responseFactory
            ->createResponse(204)
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }
}
