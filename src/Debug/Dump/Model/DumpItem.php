<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Model;

final class DumpItem
{
    public function __construct(
        private readonly int $index,
        private readonly DumpNode $value,
    ) {}

    public function index(): int
    {
        return $this->index;
    }

    public function value(): DumpNode
    {
        return $this->value;
    }
}
