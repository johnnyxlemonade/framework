<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\BuiltInApiScope;
use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;
use LogicException;

/**
 * Merges ordered API definitions into one normalized immutable runtime configuration.
 */
final readonly class ApiConfigResolver
{
    /**
     * Applies definitions in order while retaining framework defaults only for omitted values.
     *
     * @throws LogicException When an endpoint provider or explicit access mode is invalid.
     */
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
                $enabled = ConfigValueNormalizer::boolOr($data['enabled'], $enabled);
            }

            if (array_key_exists('prefix', $data)) {
                $prefix = $this->normalizePrefix($data['prefix']);
            }

            if (array_key_exists('endpoint_providers', $data)) {
                $endpointProviders = $this->mergeEndpointProviders($endpointProviders, $data['endpoint_providers']);
            }

            $security = ConfigValueNormalizer::assoc($data['security'] ?? null);
            $staticBearer = ConfigValueNormalizer::assoc($security['static_bearer'] ?? null);

            if (array_key_exists('enabled', $staticBearer)) {
                $staticBearerEnabled = ConfigValueNormalizer::boolOr(
                    $staticBearer['enabled'],
                    $staticBearerEnabled,
                );
            }

            if (array_key_exists('token', $staticBearer)) {
                $staticBearerToken = ConfigValueNormalizer::nullableString($staticBearer['token']);
            }

            if (array_key_exists('scopes', $staticBearer)) {
                $staticBearerScopes = $this->normalizeScopes(
                    $staticBearer['scopes'],
                    [BuiltInApiScope::ApiAdmin->value],
                );
            }

            $framework = ConfigValueNormalizer::assoc($data['framework'] ?? null);

            if (array_key_exists('enabled', $framework)) {
                $frameworkEnabled = ConfigValueNormalizer::boolOr($framework['enabled'], $frameworkEnabled);
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
     * Appends valid provider classes without duplicating earlier declarations.
     *
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
                /**
                 * @var class-string<ApiEndpointProviderInterface> $providerClass
                 */
                $existing[] = $providerClass;
            }
        }

        return $existing;
    }

    /**
     * Applies one partial framework endpoint override to the accumulated state.
     *
     * @param array{enabled: bool, route: non-empty-string, access: ApiAccess, scopes: list<non-empty-string>} $state
     * @return array{enabled: bool, route: non-empty-string, access: ApiAccess, scopes: list<non-empty-string>}
     */
    private function mergeEndpointConfig(array $state, mixed $value): array
    {
        $config = ConfigValueNormalizer::assoc($value);

        if (array_key_exists('enabled', $config)) {
            $state['enabled'] = ConfigValueNormalizer::boolOr($config['enabled'], $state['enabled']);
        }

        if (array_key_exists('route', $config)) {
            $state['route'] = $this->normalizeRoute($config['route'], $state['route']);
        }

        if (array_key_exists('access', $config)) {
            $state['access'] = $this->resolveAccess($config['access']);
        }

        if (array_key_exists('scopes', $config)) {
            $state['scopes'] = $this->normalizeScopes($config['scopes'], $state['scopes']);
        }

        return $state;
    }

    /**
     * Creates the normalized state used while endpoint definitions are merged.
     *
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

    private function resolveAccess(mixed $value): ApiAccess
    {
        if (!is_string($value)) {
            throw new LogicException(sprintf(
                'Configured API access mode must be a string; received %s.',
                get_debug_type($value),
            ));
        }

        $normalized = strtolower(trim($value));
        $resolved = ApiAccess::tryFrom($normalized);

        if ($resolved === null) {
            throw new LogicException(sprintf(
                'Unsupported API access mode "%s"; expected one of: %s.',
                $value,
                implode(', ', array_column(ApiAccess::cases(), 'value')),
            ));
        }

        return $resolved;
    }

    /**
     * Normalizes a configured scope list or retains the previous non-empty default.
     *
     * @param list<non-empty-string> $default
     * @return list<non-empty-string>
     */
    private function normalizeScopes(mixed $value, array $default): array
    {
        $scopes = ConfigValueNormalizer::stringList($value);

        return $scopes !== [] ? $scopes : $default;
    }

    private function normalizePrefix(mixed $value): string
    {
        $prefix = ConfigValueNormalizer::nullableString($value) ?? '/api';
        $normalized = '/' . trim($prefix, '/');

        return $normalized === '/' ? '' : rtrim($normalized, '/');
    }

    /**
     * Normalizes an endpoint route while retaining the previous value for missing input.
     *
     * @param non-empty-string $default
     * @return non-empty-string
     */
    private function normalizeRoute(mixed $value, string $default): string
    {
        $route = ConfigValueNormalizer::nullableString($value);
        if ($route === null) {
            return $default;
        }

        $normalized = '/' . trim($route, '/');
        $resolved = $normalized === '/' ? '/' : rtrim($normalized, '/');

        /**
         * @var non-empty-string $resolved
         */
        return $resolved;
    }

}
