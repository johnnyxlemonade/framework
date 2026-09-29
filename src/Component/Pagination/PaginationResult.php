<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

final readonly class PaginationResult
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $items,
        private PaginationState $state,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function state(): PaginationState
    {
        return $this->state;
    }
}
