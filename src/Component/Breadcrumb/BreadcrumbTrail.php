<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Breadcrumb;

final class BreadcrumbTrail
{
    /**
     * @var list<BreadcrumbItem>
     */
    private array $items = [];

    public function add(string $label, ?string $url = null, bool $active = false): self
    {
        $this->items[] = new BreadcrumbItem($label, $url, $active);

        return $this;
    }

    public function prepend(string $label, ?string $url = null, bool $active = false): self
    {
        array_unshift($this->items, new BreadcrumbItem($label, $url, $active));

        return $this;
    }

    public function clear(): self
    {
        $this->items = [];

        return $this;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<BreadcrumbItem>
     */
    public function items(): array
    {
        return $this->items;
    }
}
