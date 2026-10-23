<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Middleware;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRequestResolver;
use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\ApiIdentityTrust;
use Lemonade\Framework\Api\Security\ScopeVoter;
use Lemonade\Framework\Core\Config\AppConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces endpoint access, authenticated trust and scopes before API controller dispatch.
 *
 * Non-API and public requests pass through unchanged; authorized identities are attached to the request.
 */
final readonly class ApiAuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * Binds authorization to endpoint resolution, authentication, scope policy and Problem Details responses.
     */
    public function __construct(
        private ApiEndpointRequestResolver $endpointResolver,
        private ApiAuthenticatorInterface $authenticator,
        private ScopeVoter $scopeVoter,
        private ProblemDetailsFactory $problems,
        private AppConfig $appConfig,
    ) {
    }

    /**
     * Authorizes a resolved API endpoint or returns the corresponding 401 or 403 response.
     */
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

        if ($endpoint->access() === ApiAccess::InternalOnly && $identity->trust() !== ApiIdentityTrust::Internal) {
            return $this->problems->forbidden($request);
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
        return $this->appConfig->debug();
    }
}
