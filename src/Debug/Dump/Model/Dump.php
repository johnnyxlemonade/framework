<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Model;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;

final class Dump
{
    /**
     * @param list<DumpItem> $items
     */
    public function __construct(
        private readonly DumpContext $context,
        private readonly array $items,
    ) {}

    public function context(): DumpContext
    {
        return $this->context;
    }

    /**
     * @return list<DumpItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
