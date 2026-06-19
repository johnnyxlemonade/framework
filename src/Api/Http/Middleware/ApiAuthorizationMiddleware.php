<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Http\Middleware;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
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
        private readonly ApiEndpointRegistry $endpoints,
        private readonly ApiAuthenticatorInterface $authenticator,
        private readonly ScopeVoter $scopeVoter,
        private readonly ProblemDetailsFactory $problems,
        private readonly ApiConfig $apiConfig,
        private readonly AppConfig $appConfig,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $resolvedPath = $this->resolveRegistryPath($request->getUri()->getPath());
        if ($resolvedPath === null) {
            return $handler->handle($request);
        }

        $endpoint = $this->endpoints->findByRequest(
            method: $request->getMethod(),
            path: $resolvedPath,
        );

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

    private function resolveRegistryPath(string $requestPath): ?string
    {
        $normalizedPath = '/' . trim($requestPath, '/');
        $normalizedPath = $normalizedPath === '/' ? '/' : rtrim($normalizedPath, '/');

        $normalizedPrefix = $this->apiConfig->prefix;

        if ($normalizedPrefix === '') {
            return $normalizedPath;
        }

        if ($normalizedPath === $normalizedPrefix) {
            return '/';
        }

        if (!str_starts_with($normalizedPath, $normalizedPrefix . '/')) {
            return null;
        }

        $suffix = substr($normalizedPath, strlen($normalizedPrefix));

        return $suffix === '' ? '/' : $suffix;
    }
}
