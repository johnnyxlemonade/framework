<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination\Config;

/**
 * Holds the resolved defaults that govern pagination creation and rendering.
 */
final readonly class PaginationConfig
{
    /**
     * Initializes the immutable pagination defaults.
     *
     * @param array<string, string> $classes
     */
    public function __construct(
        public int $defaultPerPage,
        public int $maxPerPage,
        public int $visiblePages,
        public bool $showFirstLast,
        public array $classes,
    ) {
    }
}
