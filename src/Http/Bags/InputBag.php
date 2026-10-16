<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Bags;

/**
 * Provides read-only access to a named collection of request input values.
 */
final readonly class InputBag
{
    /**
     * Stores the input map without coercing its values.
     *
     * @param array<string, mixed> $items
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * Returns the complete input map.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Reports whether the key exists, including keys whose value is null.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Returns an input value or the supplied fallback when the key is absent or null.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }
}
