<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

/**
 * Holds host application service-provider classes resolved from configuration.
 */
final readonly class ProvidersConfig
{
    /** @param list<class-string> $providers */
    public function __construct(
        public array $providers,
    ) {
    }
}
