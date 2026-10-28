<?php

declare(strict_types=1);

namespace Lemonade\Framework\Observability\Http\Config;

/**
 * Controls explicit client exposure of HTTP metrics independently of environment and debug mode.
 */
final readonly class HttpDiagnosticsConfig
{
    /**
     * Defaults response diagnostics to disabled; enabling them exposes performance data to clients.
     */
    public function __construct(
        private bool $enabled = false,
    ) {
    }

    /**
     * Reports whether completed HTTP metrics may be exposed in response headers.
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }
}
