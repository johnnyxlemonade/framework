<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

/**
 * Describes one requested page and derives its offsets, navigation state and page URLs.
 */
final readonly class PaginationState
{
    /**
     * Initializes page boundaries and the URL context preserved across navigation links.
     *
     * @param array<string, scalar|null> $query
     */
    public function __construct(
        private int $currentPage,
        private int $perPage,
        private int $total,
        private string $pageName,
        private string $basePath,
        private array $query = [],
    ) {
    }

    /**
     * Returns the one-based page selected for this result set.
     */
    public function currentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Returns the number of rows assigned to each page.
     */
    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * Returns the total number of rows in the result set.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Returns the query parameter name that carries the requested page.
     */
    public function pageName(): string
    {
        return $this->pageName;
    }

    /**
     * Returns the zero-based row offset for the current page.
     */
    public function offset(): int
    {
        return max(0, ($this->currentPage - 1) * $this->perPage);
    }

    /**
     * Returns the highest valid page, with one as the minimum.
     */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    /**
     * Reports whether the result set spans more than one page.
     */
    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    /**
     * Reports whether a page before the current page exists.
     */
    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Reports whether a page after the current page exists.
     */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Returns the closest valid page before the current page.
     */
    public function prevPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    /**
     * Returns the closest valid page after the current page.
     */
    public function nextPage(): int
    {
        return min($this->lastPage(), $this->currentPage + 1);
    }

    /**
     * Builds a page URL while retaining non-empty query parameters from this state.
     */
    public function url(int $page): string
    {
        $query = $this->query;
        $query[$this->pageName] = $page;
        $query = array_filter($query, static fn(mixed $v): bool => $v !== null && $v !== '');
        if ($query === []) {
            return $this->basePath;
        }

        return $this->basePath . '?' . http_build_query($query);
    }

    /**
     * Returns a contiguous window of valid page numbers centered around the current page where possible.
     *
     * @return list<int>
     */
    public function pages(int $maxPages = 5): array
    {
        $last = $this->lastPage();
        $maxPages = max(1, $maxPages);

        if ($last <= $maxPages) {
            return range(1, $last);
        }

        $half = (int) floor($maxPages / 2);
        $start = $this->currentPage - $half;
        $end = $start + $maxPages - 1;

        if ($start < 1) {
            $start = 1;
            $end = $maxPages;
        }

        if ($end > $last) {
            $end = $last;
            $start = max(1, $last - $maxPages + 1);
        }

        return range($start, $end);
    }
}
