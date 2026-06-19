<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\BuiltInApiScope;
use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class ApiConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'api';
    }

    public function enabled(bool $enabled = true): self
    {
        return $this->set('enabled', $enabled);
    }

    public function disabled(): self
    {
        return $this->enabled(false);
    }

    public function prefix(string $prefix): self
    {
        return $this->set('prefix', $prefix);
    }

    public function endpointProvider(string $providerClass): self
    {
        return $this->append('endpoint_providers', $providerClass);
    }

    /**
     * @param list<string> $providerClasses
     */
    public function endpointProviders(array $providerClasses): self
    {
        return $this->set('endpoint_providers', array_values($providerClasses));
    }

    /**
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

    public function staticBearerDisabled(): self
    {
        return $this->set('security.static_bearer.enabled', false);
    }

    /**
     * @param list<string|int|float|bool|null> $scopes
     */
    public function staticBearerScopes(array $scopes): self
    {
        return $this->set('security.static_bearer.scopes', array_values($scopes));
    }

    public function frameworkEnabled(bool $enabled = true): self
    {
        return $this->set('framework.enabled', $enabled);
    }

    public function frameworkDisabled(): self
    {
        return $this->frameworkEnabled(false);
    }

    public function healthEnabled(): self
    {
        return $this->set('framework.health.enabled', true);
    }

    public function healthDisabled(): self
    {
        return $this->set('framework.health.enabled', false);
    }

    public function healthRoute(string $route): self
    {
        return $this->set('framework.health.route', $route);
    }

    public function healthAccess(ApiAccess|string $access): self
    {
        return $this->set('framework.health.access', $access instanceof ApiAccess ? $access->value : $access);
    }

    public function openApiEnabled(): self
    {
        return $this->set('framework.openapi.enabled', true);
    }

    public function openApiDisabled(): self
    {
        return $this->set('framework.openapi.enabled', false);
    }

    public function openApiRoute(string $route): self
    {
        return $this->set('framework.openapi.route', $route);
    }

    public function openApiAccess(ApiAccess|string $access): self
    {
        return $this->set('framework.openapi.access', $access instanceof ApiAccess ? $access->value : $access);
    }

    /**
     * @param list<string|int|float|bool|null> $scopes
     */
    public function openApiScopes(array $scopes): self
    {
        return $this->set('framework.openapi.scopes', array_values($scopes));
    }

    public function docsEnabled(): self
    {
        return $this->set('framework.docs.enabled', true);
    }

    public function docsDisabled(): self
    {
        return $this->set('framework.docs.enabled', false);
    }

    public function docsRoute(string $route): self
    {
        return $this->set('framework.docs.route', $route);
    }

    public function docsAccess(ApiAccess|string $access): self
    {
        return $this->set('framework.docs.access', $access instanceof ApiAccess ? $access->value : $access);
    }

    /**
     * @param list<string|int|float|bool|null> $scopes
     */
    public function docsScopes(array $scopes): self
    {
        return $this->set('framework.docs.scopes', array_values($scopes));
    }
}
