<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Model;

final readonly class DumpNode
{
    /**
     * @param list<DumpNode> $children
     * @param array<string, scalar|null> $meta
     */
    public function __construct(
        private string $type,
        private string $label,
        private ?string $value = null,
        private array $children = [],
        private array $meta = [],
        private bool $truncated = false,
        private bool $circular = false,
    ) {
    }

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
