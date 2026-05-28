<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump;

final class DumpOptions
{
    public function __construct(
        private readonly int $maxDepth = 6,
        private readonly int $maxItems = 100,
        private readonly int $maxStringLength = 2000,
        private readonly bool $showPrivateProperties = true,
        private readonly bool $showProtectedProperties = true,
        private readonly bool $showObjectIds = true,
        private readonly bool $includeHtmlStyles = true,
    ) {}

    public static function defaults(): self
    {
        return new self();
    }

    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    public function maxItems(): int
    {
        return $this->maxItems;
    }

    public function maxStringLength(): int
    {
        return $this->maxStringLength;
    }

    public function showPrivateProperties(): bool
    {
        return $this->showPrivateProperties;
    }

    public function showProtectedProperties(): bool
    {
        return $this->showProtectedProperties;
    }

    public function showObjectIds(): bool
    {
        return $this->showObjectIds;
    }

    public function includeHtmlStyles(): bool
    {
        return $this->includeHtmlStyles;
    }
}
