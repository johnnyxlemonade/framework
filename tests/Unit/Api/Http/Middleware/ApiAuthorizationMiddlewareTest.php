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
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistrar;
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
use Lemonade\Framework\Routing\RouteMatch;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Lemonade\Framework\Routing\Router;
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
        self::assertInstanceOf(
            RouteMatch::class,
            $handler->lastRequest?->getAttribute(RouteRequestAttributes::MATCH),
        );
    }

    public function testParameterizedProtectedEndpointCannotBypassAuthentication(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get(
            '/users/{id}',
            ControllerAction::for('X', 'y'),
            'users.show',
            'User',
            'User',
        );
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('GET', '/api/users/123'), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testPercentEncodedProtectedEndpointCannotBypassAuthentication(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get(
            '/secret',
            ControllerAction::for('X', 'y'),
            'secret.show',
            'Secret',
            'Secret',
        );
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('GET', '/api/se%63ret'), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testPercentEncodedApiPrefixCannotBypassAuthentication(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get(
            '/secret',
            ControllerAction::for('X', 'y'),
            'secret.show',
            'Secret',
            'Secret',
        );
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('GET', '/a%70i/secret'), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testWildcardEndpointUsesCanonicalRouterMatchForAuthorization(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get(
            '/files/{path:any}',
            ControllerAction::for('X', 'y'),
            'files.show',
            'File',
            'File',
        );
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('GET', '/api/files/a/b/c'), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testRouteConstraintMismatchDoesNotAuthorizeOrDispatchEndpoint(): void
    {
        $registry = new ApiEndpointRegistry();
        $action = ControllerAction::for('X', 'y');
        $registry->get('/items/{kind}', $action, 'items.show', 'Item', 'Item');
        $config = $this->config();
        $router = new Router();
        $route = $router->get('/api/items/{kind}', $action);
        $route->name('items.show');
        $route->constrainParameter('kind', ['safe']);
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
            router: $router,
            config: $config,
            registerRoutes: false,
        )->process(new ServerRequest('GET', '/api/items/unsafe'), $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testHeadUsesProtectedGetRoutePolicy(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get('/reports/{id}', ControllerAction::for('X', 'y'), 'reports.show', 'Report', 'Report');
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('HEAD', '/api/reports/7'), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testTrailingSlashUsesProtectedEndpointPolicy(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get('/reports', ControllerAction::for('X', 'y'), 'reports.index', 'Reports', 'Reports');
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('GET', '/api/reports/'), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testDispatchOverrideUsesProtectedEndpointPolicy(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get('/reports/{id}', ControllerAction::for('X', 'y'), 'reports.show', 'Report', 'Report');
        $handler = new CapturingRequestHandler();
        $request = (new ServerRequest('GET', '/localized/reports/7'))
            ->withAttribute(RouteRequestAttributes::DISPATCH_PATH, '/api/reports/7');

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process($request, $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testConflictingNonApiRouteInsideApiNamespaceFailsClosed(): void
    {
        $registry = new ApiEndpointRegistry();
        $router = new Router();
        $router->get('/api/conflict', ControllerAction::for('X', 'y'))->name('web.conflict');
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
            router: $router,
            registerRoutes: false,
        )->process(new ServerRequest('GET', '/api/conflict'), $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testExactRouteConflictCannotOverrideParameterizedEndpointPolicy(): void
    {
        $registry = new ApiEndpointRegistry();
        $apiAction = ControllerAction::for('ApiController', 'show');
        $registry->get('/users/{id}', $apiAction, 'users.show', 'User', 'User');
        $router = new Router();
        $router->get('/api/users/{id}', $apiAction)->name('users.show');
        $router->get('/api/users/admin', ControllerAction::for('WebController', 'show'))->name('web.admin');
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
            router: $router,
            registerRoutes: false,
        )->process(new ServerRequest('GET', '/api/users/admin'), $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testAutomaticOptionsForRegisteredPathContinuesToOptionsMiddleware(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get('/users', ControllerAction::for('X', 'y'), 'users.index', 'Users', 'Users');
        $handler = new CapturingRequestHandler();
        $request = new ServerRequest('OPTIONS', '/api/users');

        $response = $this->middleware(
            $registry,
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($request, $handler->lastRequest);
    }

    public function testAutomaticOptionsForMissingApiRouteFailsClosed(): void
    {
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            new ApiEndpointRegistry(),
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('OPTIONS', '/api/missing'), $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testMissingApiRouteFailsClosedWithoutCallingDownstream(): void
    {
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            new ApiEndpointRegistry(),
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('GET', '/api/missing'), $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
    }

    public function testMissingRouteBelowEncodedApiPrefixFailsClosed(): void
    {
        $handler = new CapturingRequestHandler();

        $response = $this->middleware(
            new ApiEndpointRegistry(),
            new StaticBearerTokenAuthenticator('token', ['api:admin']),
        )->process(new ServerRequest('GET', '/a%70i/missing'), $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertNull($handler->lastRequest);
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
        ?Router $router = null,
        ?ApiConfig $config = null,
        bool $registerRoutes = true,
    ): ApiAuthorizationMiddleware
    {
        $config ??= $this->config();
        $router ??= new Router();

        if ($registerRoutes) {
            (new ApiEndpointRegistrar($router, $registry))->registerRoutes($config->prefix());
        }

        return new ApiAuthorizationMiddleware(
            new ApiEndpointRequestResolver($registry, $config, $router),
            $auth,
            new ScopeVoter(),
            new ProblemDetailsFactory(new Psr17Factory()),
            new AppConfig(null, null, '', '', 'testing', $debug, '', '', ''),
            $router,
        );
    }

    private function config(): ApiConfig
    {
        return new ApiConfig(
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
