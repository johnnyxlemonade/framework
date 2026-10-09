<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Pagination;

use Lemonade\Framework\Database\QueryBuilder;

/**
 * Provides the application-facing entry point for creating and rendering pagination.
 */
final readonly class PaginationComponent
{
    /**
     * Initializes the component with the request-aware factory and shared renderer.
     */
    public function __construct(
        private PaginationFactory $factory,
        private PaginationRenderer $renderer,
    ) {
    }

    /**
     * Paginates in-memory rows using an explicit page or the current request's page parameter.
     *
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
     * Paginates database rows using an explicit page or the current request's page parameter.
     *
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

    /**
     * Renders a pagination result or state, returning an empty string when no state is supplied.
     */
    public function render(PaginationResult|PaginationState|null $pagination): string
    {
        if ($pagination instanceof PaginationResult) {
            return $this->renderer->render($pagination->state());
        }

        return $this->renderer->render($pagination);
    }
}
