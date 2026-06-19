<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\BuiltInApiScope;
use LogicException;

final class ApiConfigResolver
{
    public function resolve(ApiConfigDefinition ...$definitions): ApiConfig
    {
        $enabled = true;
        $prefix = '/api';
        $endpointProviders = [];
        $staticBearerEnabled = false;
        $staticBearerToken = null;
        $staticBearerScopes = [BuiltInApiScope::ApiAdmin->value];
        $frameworkEnabled = true;
        $health = $this->defaultEndpointConfig(
            enabled: true,
            route: '/framework/health',
            access: ApiAccess::Public,
            scopes: [],
        );
        $openApi = $this->defaultEndpointConfig(
            enabled: true,
            route: '/framework/openapi.json',
            access: ApiAccess::Protected,
            scopes: [BuiltInApiScope::OpenApiRead->value],
        );
        $docs = $this->defaultEndpointConfig(
            enabled: false,
            route: '/framework/docs',
            access: ApiAccess::Protected,
            scopes: [BuiltInApiScope::OpenApiRead->value],
        );

        foreach ($definitions as $definition) {
            $data = $definition->toArray();

            if (array_key_exists('enabled', $data)) {
                $enabled = $this->toBool($data['enabled'], $enabled);
            }

            if (array_key_exists('prefix', $data)) {
                $prefix = $this->normalizePrefix($data['prefix']);
            }

            if (array_key_exists('endpoint_providers', $data)) {
                $endpointProviders = $this->mergeEndpointProviders($endpointProviders, $data['endpoint_providers']);
            }

            $security = $this->assoc($data['security'] ?? null);
            $staticBearer = $this->assoc($security['static_bearer'] ?? null);

            if (array_key_exists('enabled', $staticBearer)) {
                $staticBearerEnabled = $this->toBool($staticBearer['enabled'], $staticBearerEnabled);
            }

            if (array_key_exists('token', $staticBearer)) {
                $staticBearerToken = $this->normalizeNullableString($staticBearer['token']);
            }

            if (array_key_exists('scopes', $staticBearer)) {
                $staticBearerScopes = $this->normalizeScopes(
                    $staticBearer['scopes'],
                    [BuiltInApiScope::ApiAdmin->value],
                );
            }

            $framework = $this->assoc($data['framework'] ?? null);

            if (array_key_exists('enabled', $framework)) {
                $frameworkEnabled = $this->toBool($framework['enabled'], $frameworkEnabled);
            }

            if (array_key_exists('health', $framework)) {
                $health = $this->mergeEndpointConfig($health, $framework['health']);
            }

            if (array_key_exists('openapi', $framework)) {
                $openApi = $this->mergeEndpointConfig($openApi, $framework['openapi']);
            }

            if (array_key_exists('docs', $framework)) {
                $docs = $this->mergeEndpointConfig($docs, $framework['docs']);
            }
        }

        return new ApiConfig(
            enabled: $enabled,
            prefix: $prefix,
            endpointProviders: $endpointProviders,
            security: new ApiSecurityConfig(
                $staticBearerEnabled && $staticBearerToken !== null
                    ? new StaticBearerConfig($staticBearerToken, $staticBearerScopes)
                    : null,
            ),
            framework: new FrameworkApiConfig(
                enabled: $frameworkEnabled,
                health: new ApiEndpointConfig(
                    enabled: $health['enabled'],
                    route: $health['route'],
                    access: $health['access'],
                    scopes: $health['scopes'],
                ),
                openapi: new ApiEndpointConfig(
                    enabled: $openApi['enabled'],
                    route: $openApi['route'],
                    access: $openApi['access'],
                    scopes: $openApi['scopes'],
                ),
                docs: new ApiEndpointConfig(
                    enabled: $docs['enabled'],
                    route: $docs['route'],
                    access: $docs['access'],
                    scopes: $docs['scopes'],
                ),
            ),
        );
    }

    /**
     * @param list<class-string<ApiEndpointProviderInterface>> $existing
     * @return list<class-string<ApiEndpointProviderInterface>>
     */
    private function mergeEndpointProviders(array $existing, mixed $value): array
    {
        if (!is_array($value)) {
            return $existing;
        }

        foreach ($value as $providerClass) {
            if (!is_string($providerClass) || trim($providerClass) === '') {
                throw new LogicException(sprintf(
                    'Configured API endpoint provider "%s" does not exist.',
                    is_scalar($providerClass) ? (string) $providerClass : get_debug_type($providerClass),
                ));
            }

            if (!class_exists($providerClass)) {
                throw new LogicException(sprintf(
                    'Configured API endpoint provider "%s" does not exist.',
                    $providerClass,
                ));
            }

            if (!is_subclass_of($providerClass, ApiEndpointProviderInterface::class)) {
                throw new LogicException(sprintf(
                    'Configured API endpoint provider "%s" must implement %s.',
                    $providerClass,
                    ApiEndpointProviderInterface::class,
                ));
            }

            if (!in_array($providerClass, $existing, true)) {
                /** @var class-string<ApiEndpointProviderInterface> $providerClass */
                $existing[] = $providerClass;
            }
        }

        return $existing;
    }

    /**
     * @param array{enabled: bool, route: non-empty-string, access: ApiAccess, scopes: list<non-empty-string>} $state
     * @return array{enabled: bool, route: non-empty-string, access: ApiAccess, scopes: list<non-empty-string>}
     */
    private function mergeEndpointConfig(array $state, mixed $value): array
    {
        $config = $this->assoc($value);

        if (array_key_exists('enabled', $config)) {
            $state['enabled'] = $this->toBool($config['enabled'], $state['enabled']);
        }

        if (array_key_exists('route', $config)) {
            $state['route'] = $this->normalizeRoute($config['route'], $state['route']);
        }

        if (array_key_exists('access', $config)) {
            $state['access'] = $this->resolveAccess($config['access'], $state['access']);
        }

        if (array_key_exists('scopes', $config)) {
            $state['scopes'] = $this->normalizeScopes($config['scopes'], $state['scopes']);
        }

        return $state;
    }

    /**
     * @param list<non-empty-string> $scopes
     * @param non-empty-string $route
     * @return array{enabled: bool, route: non-empty-string, access: ApiAccess, scopes: list<non-empty-string>}
     */
    private function defaultEndpointConfig(
        bool $enabled,
        string $route,
        ApiAccess $access,
        array $scopes,
    ): array {
        return [
            'enabled' => $enabled,
            'route' => $route,
            'access' => $access,
            'scopes' => $scopes,
        ];
    }

    private function resolveAccess(mixed $value, ApiAccess $default): ApiAccess
    {
        $normalized = $this->normalizeNullableString($value);
        if ($normalized === null) {
            return $default;
        }

        return ApiAccess::tryFrom(strtolower($normalized)) ?? $default;
    }

    /**
     * @param list<non-empty-string> $default
     * @return list<non-empty-string>
     */
    private function normalizeScopes(mixed $value, array $default): array
    {
        if (!is_array($value)) {
            return $default;
        }

        $scopes = [];

        foreach ($value as $scope) {
            if (!is_scalar($scope)) {
                continue;
            }

            $normalized = trim((string) $scope);
            if ($normalized === '' || in_array($normalized, $scopes, true)) {
                continue;
            }

            $scopes[] = $normalized;
        }

        return $scopes !== [] ? $scopes : $default;
    }

    private function normalizePrefix(mixed $value): string
    {
        $prefix = $this->normalizeNullableString($value) ?? '/api';
        $normalized = '/' . trim($prefix, '/');

        return $normalized === '/' ? '' : rtrim($normalized, '/');
    }

    /**
     * @param non-empty-string $default
     * @return non-empty-string
     */
    private function normalizeRoute(mixed $value, string $default): string
    {
        $route = $this->normalizeNullableString($value);
        if ($route === null) {
            return $default;
        }

        $normalized = '/' . trim($route, '/');
        $resolved = $normalized === '/' ? '/' : rtrim($normalized, '/');

        /** @var non-empty-string $resolved */
        return $resolved;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function assoc(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }
}
