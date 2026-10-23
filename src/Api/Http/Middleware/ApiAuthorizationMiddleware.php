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
use Lemonade\Framework\Http\Request\HttpMethod;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces endpoint access, authenticated trust and scopes before API controller dispatch.
 *
 * Non-API routes pass through unchanged. API routes receive the canonical RouteMatch only after
 * their registry binding is verified; protected routes additionally receive an authorized identity.
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
        private Router $router,
    ) {
    }

    /**
     * Matches once, fails closed for the API namespace, and dispatches only authorized endpoints.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->endpointResolver->isApiRequest($request)) {
            return $handler->handle($request);
        }

        $dispatchPath = $request->getAttribute(RouteRequestAttributes::DISPATCH_PATH);

        try {
            $match = $this->router->match(
                $request,
                is_string($dispatchPath) ? $dispatchPath : null,
            );
        } catch (RouteNotFoundException) {
            if ($this->isAutomaticOptionsRequest($request, $dispatchPath)) {
                return $handler->handle($request);
            }

            return $this->problems->notFound($request);
        }

        $endpoint = $this->endpointResolver->resolveMatch($match);

        if ($endpoint === null) {
            return $this->problems->notFound($request);
        }

        $request = $request->withAttribute(RouteRequestAttributes::MATCH, $match);

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

    private function isAutomaticOptionsRequest(
        ServerRequestInterface $request,
        mixed $dispatchPath,
    ): bool {
        if (strtoupper($request->getMethod()) !== HttpMethod::OPTIONS->value) {
            return false;
        }

        $path = is_string($dispatchPath) ? $dispatchPath : $request->getUri()->getPath();

        return !$this->router->hasExplicitRouteForPath(HttpMethod::OPTIONS, $path)
            && $this->router->allowedMethodsForPath($path) !== [];
    }
}
