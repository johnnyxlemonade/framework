<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiAccess;

/**
 * Captures the resolved route and authorization policy for one framework-owned API endpoint.
 */
final readonly class ApiEndpointConfig
{
    /**
     * Initializes an immutable endpoint policy consumed during framework endpoint registration.
     *
     * @param list<non-empty-string> $scopes
     */
    public function __construct(
        private bool $enabled,
        private string $route,
        private ApiAccess $access,
        private array $scopes = [],
    ) {
    }

    /**
     * Reports whether the framework should register this endpoint.
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Returns the endpoint path relative to the configured API prefix.
     */
    public function route(): string
    {
        return $this->route;
    }

    /**
     * Returns the authentication boundary enforced before endpoint dispatch.
     */
    public function access(): ApiAccess
    {
        return $this->access;
    }

    /**
     * Returns every scope required after the endpoint access boundary is satisfied.
     *
     * @return list<non-empty-string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }
}
