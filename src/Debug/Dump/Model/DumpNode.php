<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Model;

final class DumpNode
{
    /**
     * @param list<DumpNode> $children
     * @param array<string, scalar|null> $meta
     */
    public function __construct(
        private readonly string $type,
        private readonly string $label,
        private readonly ?string $value = null,
        private readonly array $children = [],
        private readonly array $meta = [],
        private readonly bool $truncated = false,
        private readonly bool $circular = false,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function value(): ?string
    {
        return $this->value;
    }

    /**
     * @return list<DumpNode>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    public function isTruncated(): bool
    {
        return $this->truncated;
    }

    public function isCircular(): bool
    {
        return $this->circular;
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }
}
