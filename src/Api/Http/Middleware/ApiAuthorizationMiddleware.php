<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Middleware;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRequestResolver;
use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\ScopeVoter;
use Lemonade\Framework\Core\Config\AppConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ApiAuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ApiEndpointRequestResolver $endpointResolver,
        private readonly ApiAuthenticatorInterface $authenticator,
        private readonly ScopeVoter $scopeVoter,
        private readonly ProblemDetailsFactory $problems,
        private readonly AppConfig $appConfig,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $endpoint = $this->endpointResolver->resolve($request);

        if ($endpoint === null) {
            return $handler->handle($request);
        }

        if ($endpoint->access() === ApiAccess::Public) {
            return $handler->handle($request);
        }

        if ($endpoint->access() === ApiAccess::DebugOnly && !$this->isDebug()) {
            return $this->problems->forbidden($request);
        }

        $identity = $this->authenticator->authenticate($request);

        if ($identity === null) {
            return $this->problems->unauthenticated($request);
        }

        if (!$this->scopeVoter->isGranted($identity, $endpoint->scopes())) {
            return $this->problems->forbidden($request);
        }

        return $handler->handle(
            $request->withAttribute(ApiIdentityRequestAttribute::NAME, $identity),
        );
    }

    private function isDebug(): bool
    {
        return $this->appConfig->debug;
    }
}
