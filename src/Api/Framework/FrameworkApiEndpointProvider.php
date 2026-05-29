<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Framework;

use Lemonade\Framework\Api\Documentation\DocsController;
use Lemonade\Framework\Api\Documentation\OpenApiController;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
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
        if ($this->config->bool('api.framework.health.enabled', true)) {
            $registry->get(
                path: $this->routeFromConfig('api.framework.health.route', '/framework/health'),
                handler: HealthController::class . '@show',
                name: 'framework.health',
                summary: 'Framework health check',
                description: 'Returns basic framework runtime availability status.',
                access: $this->resolveAccess(
                    $this->config->string('api.framework.health.access', ApiAccess::Public->value) ?? ApiAccess::Public->value,
                    ApiAccess::Public,
                ),
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Health'],
                ),
            );
        }

        if (!$this->config->bool('api.framework.enabled', true)) {
            return;
        }

        if ($this->config->bool('api.framework.openapi.enabled', true)) {
            $registry->get(
                path: $this->routeFromConfig('api.framework.openapi.route', '/framework/openapi.json'),
                handler: OpenApiController::class . '@show',
                name: 'framework.openapi',
                summary: 'OpenAPI specification',
                description: 'Returns generated OpenAPI specification for registered API endpoints.',
                access: $this->resolveAccess(
                    $this->config->string('api.framework.openapi.access', ApiAccess::Protected->value) ?? ApiAccess::Protected->value,
                    ApiAccess::Protected,
                ),
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $this->openApiScopes(),
                ),
            );
        }

        if ($this->config->bool('api.framework.docs.enabled', false)) {
            $registry->get(
                path: $this->routeFromConfig('api.framework.docs.route', '/framework/docs'),
                handler: DocsController::class . '@show',
                name: 'framework.docs',
                summary: 'Framework API docs',
                description: 'Returns simple human-readable API documentation.',
                access: $this->resolveAccess(
                    $this->config->string('api.framework.docs.access', ApiAccess::Protected->value) ?? ApiAccess::Protected->value,
                    ApiAccess::Protected,
                ),
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $this->docsScopes(),
                    responseContentType: 'text/html; charset=utf-8',
                ),
            );
        }
    }

    private function resolveAccess(string $value, ApiAccess $default): ApiAccess
    {
        return ApiAccess::tryFrom($value) ?? $default;
    }

    /**
     * @return list<non-empty-string>
     */
    private function openApiScopes(): array
    {
        $scopes = self::normalizeNonEmptyStringList(
            $this->config->array('api.framework.openapi.scopes', [BuiltInApiScope::OpenApiRead->value]),
        );

        return $scopes !== [] ? $scopes : [BuiltInApiScope::OpenApiRead->value];
    }

    /**
     * @return list<non-empty-string>
     */
    private function docsScopes(): array
    {
        $scopes = self::normalizeNonEmptyStringList(
            $this->config->array('api.framework.docs.scopes', [BuiltInApiScope::OpenApiRead->value]),
        );

        return $scopes !== [] ? $scopes : [BuiltInApiScope::OpenApiRead->value];
    }

    /**
     * @param array<mixed> $values
     * @return list<non-empty-string>
     */
    private static function normalizeNonEmptyStringList(array $values): array
    {
        $items = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $items[] = $value;
        }

        return $items;
    }

    /**
     * @param non-empty-string $key
     * @param non-empty-string $default
     * @return non-empty-string
     */
    private function routeFromConfig(string $key, string $default): string
    {
        $configured = $this->config->string($key);
        if ($configured === null || trim($configured) === '') {
            return $default;
        }

        return $configured;
    }
}
