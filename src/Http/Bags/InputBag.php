<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

final class InputBag
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private readonly array $items = []) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }
}
