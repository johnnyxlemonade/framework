<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

use Lemonade\Framework\Database\QueryBuilder;

final class PaginationComponent
{
    public function __construct(
        private readonly PaginationFactory $factory,
        private readonly PaginationRenderer $renderer,
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
        return $this->factory->fromArray($items, $page, $perPage, $pageName, $basePath, $query);
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
        return $this->factory->fromQueryBuilder($builder, $page, $perPage, $pageName, $basePath, $query);
    }

    public function render(PaginationResult|PaginationState|null $pagination): string
    {
        if ($pagination instanceof PaginationResult) {
            return $this->renderer->render($pagination->state());
        }

        return $this->renderer->render($pagination);
    }
}
