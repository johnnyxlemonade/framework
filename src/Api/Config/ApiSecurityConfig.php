<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Config;

/**
 * Selects the built-in API authentication configuration, or disables built-in authentication.
 */
final readonly class ApiSecurityConfig
{
    /**
     * Captures the optional static bearer settings used by the API service provider.
     */
    public function __construct(
        private ?StaticBearerConfig $staticBearer,
    ) {
    }

    /**
     * Returns built-in bearer authentication settings, or null when that authenticator is disabled.
     */
    public function staticBearer(): ?StaticBearerConfig
    {
        return $this->staticBearer;
    }
}
