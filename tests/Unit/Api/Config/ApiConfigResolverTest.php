<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Config;

use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Api\Config\ApiConfigResolver;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use PHPUnit\Framework\TestCase;

final class ApiConfigResolverTest extends TestCase
{
    public function testFrameworkDefaultDefinitionResolvesToTypedRuntimeConfig(): void
    {
        $config = (new ApiConfigResolver())->resolve(
            ApiConfigDefinition::create()
                ->enabled()
                ->prefix('/api')
                ->staticBearerDisabled()
                ->frameworkEnabled()
                ->healthEnabled()
                ->healthRoute('/framework/health')
                ->healthAccess('public')
                ->openApiEnabled()
                ->openApiRoute('/framework/openapi.json')
                ->openApiAccess('protected')
                ->openApiScopes(['openapi:read'])
                ->docsDisabled()
                ->docsRoute('/framework/docs')
                ->docsAccess('protected')
                ->docsScopes(['openapi:read']),
        );

        self::assertTrue($config->enabled);
        self::assertSame('/api', $config->prefix);
        self::assertNull($config->security->staticBearer);
        self::assertTrue($config->framework->enabled);
        self::assertTrue($config->framework->health->enabled);
        self::assertFalse($config->framework->docs->enabled);
        self::assertSame(['openapi:read'], $config->framework->openapi->scopes);
    }

    public function testApplicationOverrideDefinitionOverridesDefaults(): void
    {
        $config = (new ApiConfigResolver())->resolve(
            ApiConfigDefinition::create()
                ->enabled()
                ->prefix('/api')
                ->docsDisabled(),
            ApiConfigDefinition::create()
                ->prefix('internal')
                ->staticBearer('secret-token', [' admin ', '', 'openapi:read', 'admin'])
                ->docsEnabled(),
        );

        self::assertSame('/internal', $config->prefix);
        self::assertNotNull($config->security->staticBearer);
        self::assertSame('secret-token', $config->security->staticBearer->token);
        self::assertSame(['admin', 'openapi:read'], $config->security->staticBearer->scopes);
        self::assertTrue($config->framework->docs->enabled);
    }

    public function testMultipleDefinitionsMergeEndpointProviders(): void
    {
        $config = (new ApiConfigResolver())->resolve(
            ApiConfigDefinition::create()
                ->endpointProvider(TestApiEndpointProviderOne::class),
            ApiConfigDefinition::create()
                ->endpointProvider(TestApiEndpointProviderTwo::class),
        );

        self::assertSame([
            TestApiEndpointProviderOne::class,
            TestApiEndpointProviderTwo::class,
        ], $config->endpointProviders);
    }

    public function testInvalidEndpointProviderClassThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);

        (new ApiConfigResolver())->resolve(
            ApiConfigDefinition::create()->endpointProviders([
                TestInvalidApiEndpointProvider::class,
            ]),
        );
    }

    public function testInvalidScopesAreNormalizedToDefaultsWhenEmpty(): void
    {
        $config = (new ApiConfigResolver())->resolve(
            ApiConfigDefinition::create()
                ->staticBearer('secret-token', ['', '   ', null]),
        );

        self::assertNotNull($config->security->staticBearer);
        self::assertSame(['api:admin'], $config->security->staticBearer->scopes);
    }

    public function testEndpointScopesOverrideUsesExplicitReplaceRule(): void
    {
        $config = (new ApiConfigResolver())->resolve(
            ApiConfigDefinition::create()->docsScopes(['openapi:read']),
            ApiConfigDefinition::create()->docsScopes(['docs:read', 'openapi:read', 'docs:read']),
        );

        self::assertSame(['docs:read', 'openapi:read'], $config->framework->docs->scopes);
    }
}

final class TestApiEndpointProviderOne implements ApiEndpointProviderInterface
{
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get('/one', 'One@show', 'one', 'One', 'One', metadata: new ApiEndpointMetadata());
    }
}

final class TestApiEndpointProviderTwo implements ApiEndpointProviderInterface
{
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get('/two', 'Two@show', 'two', 'Two', 'Two', metadata: new ApiEndpointMetadata());
    }
}

final class TestInvalidApiEndpointProvider {}
