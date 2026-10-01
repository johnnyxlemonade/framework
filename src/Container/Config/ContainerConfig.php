<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

/**
 * Holds the application policy for resolving unbound concrete services.
 */
final readonly class ContainerConfig
{
    /**
     * Initializes the container's autowiring policy.
     */
    public function __construct(
        public AutowireMode $autowire,
    ) {
    }
}
