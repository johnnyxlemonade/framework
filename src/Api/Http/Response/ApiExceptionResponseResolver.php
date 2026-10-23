<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Response;

use Lemonade\Framework\Api\Endpoint\ApiEndpointRequestResolver;
use Lemonade\Framework\Http\Error\ExceptionResponseResolverInterface;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Converts failures for registered API endpoints into safe Problem Details responses.
 *
 * Requests outside the API registry remain unclaimed for another HTTP resolver.
 */
final readonly class ApiExceptionResponseResolver implements ExceptionResponseResolverInterface
{
    /**
     * Binds exception classification to canonical endpoint matching and problem response creation.
     */
    public function __construct(
        private ApiEndpointRequestResolver $endpointResolver,
        private ProblemDetailsFactory $problems,
    ) {
    }

    /**
     * Returns a 404 for routing failures, a generic 500 for other API failures, or null outside the API.
     */
    public function resolve(
        Throwable $exception,
        ServerRequestInterface $request,
    ): ?ResponseInterface {
        if ($this->endpointResolver->resolve($request) === null) {
            return null;
        }

        if ($exception instanceof RouteNotFoundException || $exception instanceof NotFoundHttpException) {
            return $this->problems->notFound($request);
        }

        return $this->problems->internalServerError($request);
    }
}
