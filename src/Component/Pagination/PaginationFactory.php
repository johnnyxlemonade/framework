<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

use Lemonade\Framework\Database\QueryBuilder;
use Psr\Http\Message\ServerRequestInterface;

final class PaginationFactory
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly int $defaultPerPage = 20,
        private readonly int $maxPerPage = 200,
    ) {}

    /**
     * @param list<array<string, mixed>> $items
     * @param array<string, scalar|null> $query
     */
    public function fromArray(
        array $items,
        ?int $page = null,
        ?int $perPage = null,
        string $pageName = 'page',
        ?string $basePath = null,
        ?array $query = null,
    ): PaginationResult {
        $resolvedPerPage = $this->normalizePerPage($perPage);
        $resolvedPage = $this->resolvePage($pageName, $page);
        $total = count($items);
        $offset = max(0, ($resolvedPage - 1) * $resolvedPerPage);
        $slice = array_slice($items, $offset, $resolvedPerPage);

        $state = new PaginationState(
            currentPage: $resolvedPage,
            perPage: $resolvedPerPage,
            total: $total,
            pageName: $pageName,
            basePath: $basePath ?? $this->request->getUri()->getPath(),
            query: $query ?? $this->normalizeQuery($this->request->getQueryParams()),
        );

        return new PaginationResult($slice, $state);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    public function fromQueryBuilder(
        QueryBuilder $builder,
        ?int $page = null,
        ?int $perPage = null,
        string $pageName = 'page',
        ?string $basePath = null,
        ?array $query = null,
    ): PaginationResult {
        $resolvedPerPage = $this->normalizePerPage($perPage);
        $resolvedPage = $this->resolvePage($pageName, $page);
        $total = $builder->countAllResults();
        $offset = max(0, ($resolvedPage - 1) * $resolvedPerPage);
        $rows = $builder->limit($resolvedPerPage, $offset)->getArray();

        $state = new PaginationState(
            currentPage: $resolvedPage,
            perPage: $resolvedPerPage,
            total: $total,
            pageName: $pageName,
            basePath: $basePath ?? $this->request->getUri()->getPath(),
            query: $query ?? $this->normalizeQuery($this->request->getQueryParams()),
        );

        return new PaginationResult($rows, $state);
    }

    private function resolvePage(string $pageName, ?int $explicitPage): int
    {
        if ($explicitPage !== null) {
            return max(1, $explicitPage);
        }

        $query = $this->request->getQueryParams();
        $value = $query[$pageName] ?? 1;

        if (is_int($value)) {
            return max(1, $value);
        }

        if (is_float($value)) {
            return max(1, (int) $value);
        }

        if (is_string($value) && is_numeric($value)) {
            return max(1, (int) $value);
        }

        return 1;
    }

    private function normalizePerPage(?int $perPage): int
    {
        if ($perPage === null || $perPage < 1) {
            return $this->defaultPerPage;
        }

        return min($this->maxPerPage, $perPage);
    }

    /**
     * @param array<mixed> $query
     * @return array<string, scalar|null>
     */
    private function normalizeQuery(array $query): array
    {
        $normalized = [];

        foreach ($query as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
