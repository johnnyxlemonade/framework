<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Health;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Http\Config\CorsConfig;

/**
 * Carries the immutable configuration subset required by the pre-bootstrap health fast path.
 */
final readonly class FrameworkHealthConfigSnapshot
{
    /**
     * Captures resolved API, application and CORS policies as one atomic snapshot.
     */
    public function __construct(
        private ApiConfig $api,
        private AppConfig $app,
        private ?CorsConfig $cors,
    ) {
    }

    /**
     * Returns the resolved API policy used to match and authorize the health endpoint.
     */
    public function api(): ApiConfig
    {
        return $this->api;
    }

    /**
     * Returns application settings needed for health response metadata.
     */
    public function app(): AppConfig
    {
        return $this->app;
    }

    /**
     * Returns the optional CORS policy applied to the fast-path response.
     */
    public function cors(): ?CorsConfig
    {
        return $this->cors;
    }

}
