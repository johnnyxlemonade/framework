<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

/**
 * Holds ordered caller-owned breadcrumb items until a renderer presents them.
 */
final class BreadcrumbTrail
{
    /**
     * @var list<BreadcrumbItem>
     */
    private array $items = [];

    /**
     * Appends one item after existing trail items.
     */
    public function add(string $label, ?string $url = null): self
    {
        $this->items[] = new BreadcrumbItem($label, $url);

        return $this;
    }

    /**
     * Inserts one item before all existing trail items.
     */
    public function prepend(string $label, ?string $url = null): self
    {
        array_unshift($this->items, new BreadcrumbItem($label, $url));

        return $this;
    }

    /**
     * Removes every item while preserving the mutable trail instance.
     */
    public function clear(): self
    {
        $this->items = [];

        return $this;
    }

    /**
     * Returns the number of items in display order.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Returns the ordered items without applying presentation-specific state.
     *
     * @return list<BreadcrumbItem>
     */
    public function items(): array
    {
        return $this->items;
    }
}
