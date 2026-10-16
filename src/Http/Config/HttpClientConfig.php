<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Config;

/**
 * Carries transport-neutral timeout and TLS settings for configured HTTP clients.
 */
final readonly class HttpClientConfig
{
    /**
     * Initializes one immutable client policy shared by the selected transport adapter.
     */
    public function __construct(
        public float $timeout,
        public float $connectTimeout,
        public bool $verifySsl,
    ) {
    }
}
