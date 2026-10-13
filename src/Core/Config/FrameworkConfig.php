<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config;

/**
 * Holds the framework service-provider classes resolved from configuration.
 */
final readonly class FrameworkConfig
{
    /** @param list<class-string> $providers */
    public function __construct(
        public array $providers,
    ) {
    }
}
