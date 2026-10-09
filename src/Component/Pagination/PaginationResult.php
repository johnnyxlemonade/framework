<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

/**
 * Couples the rows of one page with the state required to navigate its result set.
 */
final readonly class PaginationResult
{
    /**
     * Initializes a page's rows and its corresponding navigation state.
     *
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        private array $items,
        private PaginationState $state,
    ) {
    }

    /**
     * Returns the rows belonging to the current page.
     *
     * @return list<array<string, mixed>>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Returns the immutable navigation state for these rows.
     */
    public function state(): PaginationState
    {
        return $this->state;
    }
}
