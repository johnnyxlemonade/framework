<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Http\Middleware;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Http\Middleware\ApiIdentityRequestAttribute;
use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Lemonade\Framework\Api\Security\ApiIdentity;
use Lemonade\Framework\Api\Security\ScopeVoter;
use Lemonade\Framework\Api\Security\StaticBearerTokenAuthenticator;
use Lemonade\Framework\Core\Config;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ApiAuthorizationMiddlewareTest extends TestCase
{
    public function testNonApiRequestPassesThrough(): void
    {
        $middleware = $this->middleware(new ApiEndpointRegistry(), new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $request = new ServerRequest('GET', '/not-api');
        $handler = new CapturingRequestHandler();

        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($request, $handler->lastRequest);
    }

    public function testPublicEndpointPassesWithoutToken(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/public', 'X@y', 'public.index', 'Public', 'Public', ApiAccess::Public);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $handler = new CapturingRequestHandler();

        $response = $middleware->process(new ServerRequest('GET', '/api/public'), $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testProtectedEndpointWithoutTokenReturns401(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/protected', 'X@y', 'protected.index', 'Protected', 'Protected', ApiAccess::Protected);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $response = $middleware->process(new ServerRequest('GET', '/api/protected'), new CapturingRequestHandler());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testProtectedEndpointWithInvalidTokenReturns401(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/protected', 'X@y', 'protected.index', 'Protected', 'Protected', ApiAccess::Protected);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $request = (new ServerRequest('GET', '/api/protected'))->withHeader('Authorization', 'Bearer invalid');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testProtectedEndpointWithValidTokenWithoutScopeReturns403(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/protected', 'X@y', 'protected.index', 'Protected', 'Protected', ApiAccess::Protected, scopes: ['openapi:read']);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['framework:read']));
        $request = (new ServerRequest('GET', '/api/protected'))->withHeader('Authorization', 'Bearer token');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testProtectedEndpointWithApiAdminPasses(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/protected', 'X@y', 'protected.index', 'Protected', 'Protected', ApiAccess::Protected, scopes: ['openapi:read']);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $request = (new ServerRequest('GET', '/api/protected'))->withHeader('Authorization', 'Bearer token');
        $handler = new CapturingRequestHandler();
        $response = $middleware->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertInstanceOf(ApiIdentity::class, $handler->lastRequest?->getAttribute(ApiIdentityRequestAttribute::NAME));
    }

    public function testProtectedEndpointWithRequiredScopePasses(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/protected', 'X@y', 'protected.index', 'Protected', 'Protected', ApiAccess::Protected, scopes: ['openapi:read']);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['openapi:read']));
        $request = (new ServerRequest('GET', '/api/protected'))->withHeader('Authorization', 'Bearer token');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    private function middleware(ApiEndpointRegistry $registry, StaticBearerTokenAuthenticator $auth): ApiAuthorizationMiddleware
    {
        return new ApiAuthorizationMiddleware(
            $registry,
            $auth,
            new ScopeVoter(),
            new ProblemDetailsFactory(new Psr17Factory()),
            new Config(['app' => ['debug' => false]]),
        );
    }
}

final class CapturingRequestHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $lastRequest = null;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        return new Response(200);
    }
}
