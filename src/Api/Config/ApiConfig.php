<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;

/**
 * Carries the resolved API runtime policy and its nested security and framework settings.
 */
final readonly class ApiConfig
{
    /**
     * Captures immutable API configuration consumed by endpoint registration and middleware.
     *
     * @param list<class-string<ApiEndpointProviderInterface>> $endpointProviders
     */
    public function __construct(
        private bool $enabled,
        private string $prefix,
        private array $endpointProviders,
        private ApiSecurityConfig $security,
        private FrameworkApiConfig $framework,
    ) {
    }

    /**
     * Reports whether API route registration is enabled.
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Returns the normalized path prefix shared by all API endpoints.
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Returns application endpoint provider classes in registration order.
     *
     * @return list<class-string<ApiEndpointProviderInterface>>
     */
    public function endpointProviders(): array
    {
        return $this->endpointProviders;
    }

    /**
     * Returns the resolved API authentication settings.
     */
    public function security(): ApiSecurityConfig
    {
        return $this->security;
    }

    /**
     * Returns the built-in health and documentation endpoint settings.
     */
    public function framework(): FrameworkApiConfig
    {
        return $this->framework;
    }
}
