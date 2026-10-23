<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Http\Middleware;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiEndpointConfig;
use Lemonade\Framework\Api\Config\ApiSecurityConfig;
use Lemonade\Framework\Api\Config\FrameworkApiConfig;
use Lemonade\Framework\Api\Config\StaticBearerConfig;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRequestResolver;
use Lemonade\Framework\Api\Http\Middleware\ApiAuthorizationMiddleware;
use Lemonade\Framework\Api\Http\Middleware\ApiIdentityRequestAttribute;
use Lemonade\Framework\Api\Http\Response\ProblemDetailsFactory;
use Lemonade\Framework\Api\Security\ApiAuthenticatorInterface;
use Lemonade\Framework\Api\Security\ApiIdentity;
use Lemonade\Framework\Api\Security\ApiIdentityTrust;
use Lemonade\Framework\Api\Security\ScopeVoter;
use Lemonade\Framework\Api\Security\StaticBearerTokenAuthenticator;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Routing\ControllerAction;
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
        $registry->add('GET', '/public', ControllerAction::for('X', 'y'), 'public.index', 'Public', 'Public', ApiAccess::Public);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $handler = new CapturingRequestHandler();

        $response = $middleware->process(new ServerRequest('GET', '/api/public'), $handler);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testProtectedEndpointWithoutTokenReturns401(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/protected', ControllerAction::for('X', 'y'), 'protected.index', 'Protected', 'Protected', ApiAccess::Protected);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $response = $middleware->process(new ServerRequest('GET', '/api/protected'), new CapturingRequestHandler());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testProtectedEndpointWithInvalidTokenReturns401(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/protected', ControllerAction::for('X', 'y'), 'protected.index', 'Protected', 'Protected', ApiAccess::Protected);

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['api:admin']));
        $request = (new ServerRequest('GET', '/api/protected'))->withHeader('Authorization', 'Bearer invalid');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testProtectedEndpointWithValidTokenWithoutScopeReturns403(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add(
            'GET',
            '/protected',
            ControllerAction::for('X', 'y'),
            'protected.index',
            'Protected',
            'Protected',
            ApiAccess::Protected,
            new ApiEndpointMetadata(scopes: ['openapi:read']),
        );

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['framework:read']));
        $request = (new ServerRequest('GET', '/api/protected'))->withHeader('Authorization', 'Bearer token');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testProtectedEndpointWithApiAdminPasses(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add(
            'GET',
            '/protected',
            ControllerAction::for('X', 'y'),
            'protected.index',
            'Protected',
            'Protected',
            ApiAccess::Protected,
            new ApiEndpointMetadata(scopes: ['openapi:read']),
        );

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
        $registry->add(
            'GET',
            '/protected',
            ControllerAction::for('X', 'y'),
            'protected.index',
            'Protected',
            'Protected',
            ApiAccess::Protected,
            new ApiEndpointMetadata(scopes: ['openapi:read']),
        );

        $middleware = $this->middleware($registry, new StaticBearerTokenAuthenticator('token', ['openapi:read']));
        $request = (new ServerRequest('GET', '/api/protected'))->withHeader('Authorization', 'Bearer token');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    public function testDebugEndpointRemainsForbiddenWhenDebugIsDisabled(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/debug', ControllerAction::for('X', 'y'), 'debug.index', 'Debug', 'Debug', ApiAccess::DebugOnly);

        $middleware = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        );
        $request = (new ServerRequest('GET', '/api/debug'))->withHeader('Authorization', 'Bearer token');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testDebugEndpointRemainsAvailableWithAuthenticationWhenDebugIsEnabled(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->add('GET', '/debug', ControllerAction::for('X', 'y'), 'debug.index', 'Debug', 'Debug', ApiAccess::DebugOnly);

        $middleware = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
            debug: true,
        );
        $request = (new ServerRequest('GET', '/api/debug'))->withHeader('Authorization', 'Bearer token');
        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    public function testInternalEndpointWithoutIdentityReturns401(): void
    {
        $registry = $this->internalEndpointRegistry();
        $middleware = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        );

        $response = $middleware->process(
            new ServerRequest('GET', '/api/internal'),
            new CapturingRequestHandler(),
        );

        self::assertSame(401, $response->getStatusCode());
    }

    public function testInternalEndpointRejectsExternalStaticTokenWithApiAdmin(): void
    {
        $registry = $this->internalEndpointRegistry();
        $middleware = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        );
        $request = (new ServerRequest('GET', '/api/internal'))->withHeader('Authorization', 'Bearer token');

        $response = $middleware->process($request, new CapturingRequestHandler());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testInternalEndpointDoesNotInferTrustFromIdentityTypeOrScopes(): void
    {
        $registry = $this->internalEndpointRegistry();
        $identity = new ApiIdentity(
            id: 'external-service',
            type: 'internal',
            scopes: ['internal:read', 'api:admin'],
        );
        $middleware = $this->middleware($registry, new FixedApiAuthenticator($identity));

        $response = $middleware->process(
            new ServerRequest('GET', '/api/internal'),
            new CapturingRequestHandler(),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testInternalEndpointRequiresScopesAfterTrustCheck(): void
    {
        $registry = $this->internalEndpointRegistry();
        $identity = new ApiIdentity(
            id: 'internal-service',
            type: 'service',
            scopes: ['unrelated:read'],
            trust: ApiIdentityTrust::Internal,
        );
        $middleware = $this->middleware($registry, new FixedApiAuthenticator($identity));

        $response = $middleware->process(
            new ServerRequest('GET', '/api/internal'),
            new CapturingRequestHandler(),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testInternalEndpointAllowsInternalIdentityWithRequiredScopes(): void
    {
        $registry = $this->internalEndpointRegistry();
        $identity = new ApiIdentity(
            id: 'internal-service',
            type: 'service',
            scopes: ['internal:read'],
            trust: ApiIdentityTrust::Internal,
        );
        $middleware = $this->middleware($registry, new FixedApiAuthenticator($identity));
        $handler = new CapturingRequestHandler();

        $response = $middleware->process(new ServerRequest('GET', '/api/internal'), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($identity, $handler->lastRequest?->getAttribute(ApiIdentityRequestAttribute::NAME));
    }

    public function testInternalEndpointAllowsInternalApiAdminToBypassScopes(): void
    {
        $registry = $this->internalEndpointRegistry();
        $identity = new ApiIdentity(
            id: 'internal-admin',
            type: 'service',
            scopes: ['api:admin'],
            trust: ApiIdentityTrust::Internal,
        );
        $middleware = $this->middleware($registry, new FixedApiAuthenticator($identity));

        $response = $middleware->process(
            new ServerRequest('GET', '/api/internal'),
            new CapturingRequestHandler(),
        );

        self::assertSame(200, $response->getStatusCode());
    }

    private function internalEndpointRegistry(): ApiEndpointRegistry
    {
        $registry = new ApiEndpointRegistry();
        $registry->add(
            'GET',
            '/internal',
            ControllerAction::for('X', 'y'),
            'internal.index',
            'Internal',
            'Internal',
            ApiAccess::InternalOnly,
            new ApiEndpointMetadata(scopes: ['internal:read']),
        );

        return $registry;
    }

    private function middleware(
        ApiEndpointRegistry $registry,
        ApiAuthenticatorInterface $auth,
        bool $debug = false,
    ): ApiAuthorizationMiddleware
    {
        $config = new ApiConfig(
            enabled: true,
            prefix: '/api',
            endpointProviders: [],
            security: new ApiSecurityConfig(new StaticBearerConfig('token', ['api:admin'])),
            framework: new FrameworkApiConfig(
                enabled: true,
                health: new ApiEndpointConfig(true, '/framework/health', ApiAccess::Public),
                openapi: new ApiEndpointConfig(true, '/framework/openapi.json', ApiAccess::Protected, ['openapi:read']),
                docs: new ApiEndpointConfig(false, '/framework/docs', ApiAccess::Protected, ['openapi:read']),
            ),
        );

        return new ApiAuthorizationMiddleware(
            new ApiEndpointRequestResolver($registry, $config),
            $auth,
            new ScopeVoter(),
            new ProblemDetailsFactory(new Psr17Factory()),
            new AppConfig(null, null, '', '', 'testing', $debug, '', '', ''),
        );
    }
}

final readonly class FixedApiAuthenticator implements ApiAuthenticatorInterface
{
    public function __construct(
        private ?ApiIdentity $identity,
    ) {
    }

    public function authenticate(ServerRequestInterface $request): ?ApiIdentity
    {
        unset($request);

        return $this->identity;
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
