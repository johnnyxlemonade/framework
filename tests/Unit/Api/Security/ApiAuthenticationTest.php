<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Security;

use Lemonade\Framework\Api\Security\ApiIdentity;
use Lemonade\Framework\Api\Security\ApiIdentityTrust;
use Lemonade\Framework\Api\Security\NullApiAuthenticator;
use Lemonade\Framework\Api\Security\ScopeVoter;
use Lemonade\Framework\Api\Security\StaticBearerTokenAuthenticator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ApiAuthenticationTest extends TestCase
{
    public function testStaticBearerAuthenticatorReturnsIdentityForValidToken(): void
    {
        $auth = new StaticBearerTokenAuthenticator('secret', ['framework:diagnostics']);
        $identity = $auth->authenticate((new ServerRequest('GET', '/'))->withHeader('Authorization', 'Bearer secret'));

        self::assertInstanceOf(ApiIdentity::class, $identity);
        self::assertSame(['framework:diagnostics'], $identity->scopes());
        self::assertSame(ApiIdentityTrust::External, $identity->trust());
    }

    public function testExistingApiIdentityConstructorDefaultsToExternalTrust(): void
    {
        $identity = new ApiIdentity('1', 'custom', ['api:admin'], 'Custom identity');

        self::assertSame(ApiIdentityTrust::External, $identity->trust());
    }

    public function testApiIdentityAcceptsExplicitInternalTrust(): void
    {
        $identity = new ApiIdentity(
            id: 'service-1',
            type: 'service',
            scopes: ['internal:read'],
            name: 'Internal service',
            trust: ApiIdentityTrust::Internal,
        );

        self::assertSame(ApiIdentityTrust::Internal, $identity->trust());
    }

    public function testStaticBearerAuthenticatorReturnsNullForInvalidToken(): void
    {
        $auth = new StaticBearerTokenAuthenticator('secret', ['api:admin']);
        $identity = $auth->authenticate((new ServerRequest('GET', '/'))->withHeader('Authorization', 'Bearer invalid'));

        self::assertNull($identity);
    }

    public function testStaticBearerAuthenticatorReturnsNullForMissingAuthorizationHeader(): void
    {
        $auth = new StaticBearerTokenAuthenticator('secret', ['api:admin']);

        self::assertNull($auth->authenticate(new ServerRequest('GET', '/')));
    }

    public function testStaticBearerAuthenticatorWithoutConfiguredTokenReturnsNull(): void
    {
        $auth = new StaticBearerTokenAuthenticator(null, ['api:admin']);

        self::assertNull($auth->authenticate((new ServerRequest('GET', '/'))->withHeader('Authorization', 'Bearer anything')));
    }

    public function testNullApiAuthenticatorAlwaysReturnsNull(): void
    {
        $auth = new NullApiAuthenticator();

        self::assertNull($auth->authenticate(new ServerRequest('GET', '/')));
    }

    public function testScopeVoterAllowsApiAdminSuperScope(): void
    {
        $voter = new ScopeVoter();
        $identity = new ApiIdentity('1', 'static', ['api:admin']);

        self::assertTrue($voter->isGranted($identity, ['openapi:read', 'tokens:revoke']));
    }

    public function testScopeVoterRequiresAllScopes(): void
    {
        $voter = new ScopeVoter();
        $identity = new ApiIdentity('1', 'static', ['framework:read']);

        self::assertFalse($voter->isGranted($identity, ['framework:read', 'framework:diagnostics']));
    }
}
