<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

/**
 * Groups the resolved registration policies for framework-owned API endpoints.
 *
 * Health remains independently configurable when the other framework endpoints are disabled.
 */
final readonly class FrameworkApiConfig
{
    /**
     * Captures immutable health, OpenAPI and documentation endpoint settings.
     */
    public function __construct(
        private bool $enabled,
        private ApiEndpointConfig $health,
        private ApiEndpointConfig $openapi,
        private ApiEndpointConfig $docs,
    ) {
    }

    /**
     * Reports whether framework-owned documentation endpoints may be registered.
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Returns the health endpoint policy, which remains independent of the global documentation switch.
     */
    public function health(): ApiEndpointConfig
    {
        return $this->health;
    }

    /**
     * Returns the OpenAPI specification endpoint policy.
     */
    public function openApi(): ApiEndpointConfig
    {
        return $this->openapi;
    }

    /**
     * Returns the human-readable API documentation endpoint policy.
     */
    public function docs(): ApiEndpointConfig
    {
        return $this->docs;
    }
}
