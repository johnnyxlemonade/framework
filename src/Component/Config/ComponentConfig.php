<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Config;

/**
 * Holds the resolved mapping from component names to their implementation classes.
 *
 * The mapping is assembled from registered definitions and is immutable after resolution.
 */
final readonly class ComponentConfig
{
    /**
     * Initializes the immutable component mapping produced by configuration resolution.
     *
     * @param array<string, class-string> $components
     */
    public function __construct(
        public array $components,
    ) {
    }
}
