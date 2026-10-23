<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\BuiltInApiScope;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Collects typed API configuration overrides for later ordered resolution.
 */
final class ApiConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty definition that inherits all unresolved API defaults.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the configuration module key consumed by the API resolver.
     */
    public static function moduleKey(): string
    {
        return 'api';
    }

    /**
     * Overrides whether the API module registers middleware and routes.
     */
    public function enabled(bool $enabled = true): self
    {
        return $this->set('enabled', $enabled);
    }

    /**
     * Disables API middleware and route registration.
     */
    public function disabled(): self
    {
        return $this->enabled(false);
    }

    /**
     * Overrides the shared path prefix for framework and application API endpoints.
     */
    public function prefix(string $prefix): self
    {
        return $this->set('prefix', $prefix);
    }

    /**
     * Appends one application endpoint provider while preserving declaration order.
     */
    public function endpointProvider(string $providerClass): self
    {
        return $this->append('endpoint_providers', $providerClass);
    }

    /**
     * Replaces the endpoint provider list for this definition.
     *
     * @param list<string> $providerClasses
     */
    public function endpointProviders(array $providerClasses): self
    {
        return $this->set('endpoint_providers', array_values($providerClasses));
    }

    /**
     * Enables the built-in bearer authenticator with its token and effective scopes.
     *
     * An omitted scope list grants the built-in administrative scope.
     *
     * @param list<string|int|float|bool|null> $scopes
     */
    public function staticBearer(mixed $token, array $scopes = []): self
    {
        $this->set('security.static_bearer.enabled', true);
        $this->set('security.static_bearer.token', $token);

        return $this->set(
            'security.static_bearer.scopes',
            $scopes !== [] ? array_values($scopes) : [BuiltInApiScope::ApiAdmin->value],
        );
    }

    /**
     * Disables the built-in bearer authenticator without removing its other overrides.
     */
    public function staticBearerDisabled(): self
    {
        return $this->set('security.static_bearer.enabled', false);
    }

    /**
     * Replaces the configured scopes assigned to a valid static bearer identity.
     *
     * @param list<string|int|float|bool|null> $scopes
     */
    public function staticBearerScopes(array $scopes): self
    {
        return $this->set('security.static_bearer.scopes', array_values($scopes));
    }

    /**
     * Overrides whether framework-owned API endpoints other than health are registered.
     */
    public function frameworkEnabled(bool $enabled = true): self
    {
        return $this->set('framework.enabled', $enabled);
    }

    /**
     * Disables framework-owned OpenAPI and documentation endpoints.
     */
    public function frameworkDisabled(): self
    {
        return $this->frameworkEnabled(false);
    }

    /**
     * Enables the framework health endpoint.
     */
    public function healthEnabled(): self
    {
        return $this->set('framework.health.enabled', true);
    }

    /**
     * Disables the framework health endpoint.
     */
    public function healthDisabled(): self
    {
        return $this->set('framework.health.enabled', false);
    }

    /**
     * Overrides the health path relative to the API prefix.
     */
    public function healthRoute(string $route): self
    {
        return $this->set('framework.health.route', $route);
    }

    /**
     * Overrides the authorization mode enforced for the health endpoint.
     */
    public function healthAccess(ApiAccess|string $access): self
    {
        return $this->set('framework.health.access', $access instanceof ApiAccess ? $access->value : $access);
    }

    /**
     * Enables the generated OpenAPI endpoint.
     */
    public function openApiEnabled(): self
    {
        return $this->set('framework.openapi.enabled', true);
    }

    /**
     * Disables the generated OpenAPI endpoint.
     */
    public function openApiDisabled(): self
    {
        return $this->set('framework.openapi.enabled', false);
    }

    /**
     * Overrides the OpenAPI document path relative to the API prefix.
     */
    public function openApiRoute(string $route): self
    {
        return $this->set('framework.openapi.route', $route);
    }

    /**
     * Overrides the authorization mode enforced for the OpenAPI document.
     */
    public function openApiAccess(ApiAccess|string $access): self
    {
        return $this->set('framework.openapi.access', $access instanceof ApiAccess ? $access->value : $access);
    }

    /**
     * Replaces the scopes required by the OpenAPI document endpoint.
     *
     * @param list<string|int|float|bool|null> $scopes
     */
    public function openApiScopes(array $scopes): self
    {
        return $this->set('framework.openapi.scopes', array_values($scopes));
    }

    /**
     * Enables the human-readable API documentation endpoint.
     */
    public function docsEnabled(): self
    {
        return $this->set('framework.docs.enabled', true);
    }

    /**
     * Disables the human-readable API documentation endpoint.
     */
    public function docsDisabled(): self
    {
        return $this->set('framework.docs.enabled', false);
    }

    /**
     * Overrides the documentation path relative to the API prefix.
     */
    public function docsRoute(string $route): self
    {
        return $this->set('framework.docs.route', $route);
    }

    /**
     * Overrides the authorization mode enforced for human-readable documentation.
     */
    public function docsAccess(ApiAccess|string $access): self
    {
        return $this->set('framework.docs.access', $access instanceof ApiAccess ? $access->value : $access);
    }

    /**
     * Replaces the scopes required by the human-readable documentation endpoint.
     *
     * @param list<string|int|float|bool|null> $scopes
     */
    public function docsScopes(array $scopes): self
    {
        return $this->set('framework.docs.scopes', array_values($scopes));
    }
}
