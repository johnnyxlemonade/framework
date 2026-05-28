<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use RuntimeException;

final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private array $items = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function require(string $key): mixed
    {
        $value = $this->get($key);

        if ($value === null) {
            throw new RuntimeException("Missing required config value: {$key}");
        }

        return $value;
    }

    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        if ($value === null || !is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }

    public function requiredString(string $key): string
    {
        $value = $this->string($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Missing required string config value: {$key}");
        }

        return $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $result ?? $default;
    }

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &$this->items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /**
     * @param array<string, mixed> $items
     */
    public function merge(array $items): void
    {
        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($this->items, $items);

        $this->applyReplaceOnlyKeys($merged, $items);

        $this->items = $merged;
    }

    /**
     * @param array<string, mixed> $merged
     * @param array<string, mixed> $items
     */
    private function applyReplaceOnlyKeys(array &$merged, array $items): void
    {
        if (
            isset($items['framework'])
            && is_array($items['framework'])
            && array_key_exists('providers', $items['framework'])
        ) {
            if (!isset($merged['framework']) || !is_array($merged['framework'])) {
                $merged['framework'] = [];
            }

            $merged['framework']['providers'] = $items['framework']['providers'];
        }
    }

}
