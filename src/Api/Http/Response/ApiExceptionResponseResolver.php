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

final readonly class ApiExceptionResponseResolver implements ExceptionResponseResolverInterface
{
    public function __construct(
        private ApiEndpointRequestResolver $endpointResolver,
        private ProblemDetailsFactory $problems,
    ) {
    }

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
