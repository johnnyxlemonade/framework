<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

final class PaginationState
{
    /**
     * @param array<string, scalar|null> $query
     */
    public function __construct(
        private readonly int $currentPage,
        private readonly int $perPage,
        private readonly int $total,
        private readonly string $pageName,
        private readonly string $basePath,
        private readonly array $query = [],
    ) {}

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function pageName(): string
    {
        return $this->pageName;
    }

    public function offset(): int
    {
        return max(0, ($this->currentPage - 1) * $this->perPage);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNext(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    public function prevPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function nextPage(): int
    {
        return min($this->lastPage(), $this->currentPage + 1);
    }

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
