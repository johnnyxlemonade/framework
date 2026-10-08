<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Middleware;

use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Routing\Router;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Supplies automatic OPTIONS responses when no explicit OPTIONS route owns a path.
 */
final class OptionsMiddleware implements MiddlewareInterface
{
    /**
     * Initializes automatic OPTIONS handling against the shared route collection.
     */
    public function __construct(
        private readonly Router $router,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    /**
     * Delegates non-OPTIONS and explicit routes, otherwise returns the allowed methods.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== HttpMethod::OPTIONS->value) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();

        if ($this->router->hasExplicitRouteForPath(HttpMethod::OPTIONS, $path)) {
            return $handler->handle($request);
        }

        $allowedMethods = $this->router->allowedMethodsForPath($path);
        if ($allowedMethods === []) {
            return $handler->handle($request);
        }

        return $this->responseFactory
            ->createResponse(HttpStatus::NO_CONTENT->value)
            ->withHeader('Allow', implode(', ', $allowedMethods));
    }
}
