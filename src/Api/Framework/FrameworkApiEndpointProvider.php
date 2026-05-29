<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Framework;

use Lemonade\Framework\Api\Documentation\OpenApiController;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Endpoint\BuiltInApiScope;
use Lemonade\Framework\Api\Framework\Health\HealthController;
use Lemonade\Framework\Core\Config;

final class FrameworkApiEndpointProvider implements ApiEndpointProviderInterface
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get(
            path: '/framework/health',
            handler: HealthController::class . '@show',
            name: 'framework.health',
            summary: 'Framework health check',
            description: 'Returns basic framework runtime availability status.',
            access: ApiAccess::Public,
            tags: ['Framework', 'Health'],
        );

        if (!$this->config->bool('api.framework.enabled', true)) {
            return;
        }

        if ($this->config->bool('api.framework.openapi.enabled', true)) {
            $registry->get(
                path: '/framework/openapi.json',
                handler: OpenApiController::class . '@show',
                name: 'framework.openapi',
                summary: 'OpenAPI specification',
                description: 'Returns generated OpenAPI specification for registered API endpoints.',
                access: $this->resolveAccess(
                    (string) $this->config->string('api.framework.openapi.access', ApiAccess::Protected->value),
                    ApiAccess::Protected,
                ),
                tags: ['Framework', 'Documentation'],
                scopes: $this->openApiScopes(),
            );
        }
    }

    private function resolveAccess(string $value, ApiAccess $default): ApiAccess
    {
        return ApiAccess::tryFrom($value) ?? $default;
    }

    /**
     * @return list<string>
     */
    private function openApiScopes(): array
    {
        $scopes = array_values(array_filter(
            $this->config->array('api.framework.openapi.scopes', [BuiltInApiScope::OpenApiRead->value]),
            static fn(mixed $scope): bool => is_string($scope) && trim($scope) !== '',
        ));

        return $scopes !== [] ? $scopes : [BuiltInApiScope::OpenApiRead->value];
    }
}
