<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination\Config;

final class PaginationConfig
{
    /**
     * @param array<string, string> $classes
     */
    public function __construct(
        public readonly int $defaultPerPage,
        public readonly int $maxPerPage,
        public readonly int $visiblePages,
        public readonly bool $showFirstLast,
        public readonly array $classes,
    ) {}
}
