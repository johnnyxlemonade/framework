<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination\Config;

final readonly class PaginationConfig
{
    /**
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
