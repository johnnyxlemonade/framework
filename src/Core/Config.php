<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core;

use RuntimeException;

/**
 * Mutable runtime configuration store for framework and application settings.
 *
 * The store supports dot-notation keys, provides both generic and typed access
 * helpers, and allows direct setting and recursive merging of configuration
 * values. The `framework.providers` branch uses replace-only merge semantics
 * and is not merged by numeric indexes.
 */
final class Config
{
    /**
     * Creates a configuration store with optional initial items.
     *
     * @param array<string, mixed> $items
     */
    public function __construct(
        private array $items = [],
    ) {}

    /**
     * Returns the complete current configuration state.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Returns a configuration value resolved through dot notation.
     *
     * When any path segment is missing, the provided default value is returned.
     * If the key exists and its value is explicitly `null`, `null` is returned.
     *
     * @param string $key Dot-notation configuration key to resolve.
     * @param mixed $default Fallback value returned when the key path does not exist.
     */
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

    /**
     * Returns a required configuration value.
     *
     * Values such as `false`, `0`, an empty string, and an empty array are
     * accepted. Only missing or explicit `null` values are treated as absent.
     *
     * @throws RuntimeException If the required configuration value is missing or null.
     */
    public function require(string $key): mixed
    {
        $value = $this->get($key);

        if ($value === null) {
            throw new RuntimeException("Missing required config value: {$key}");
        }

        return $value;
    }

    /**
     * Returns the resolved configuration value converted to a string when possible.
     *
     * Scalar values are cast to string. When the resolved value is `null` or is
     * not scalar, the method returns `null`. The default value is used when the
     * key path does not exist.
     */
    public function string(string $key, ?string $default = null): ?string
    {
        $value = $this->get($key, $default);

        if ($value === null || !is_scalar($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Returns a required non-empty string configuration value.
     *
     * @throws RuntimeException If the value is missing, null or an empty string.
     */
    public function requiredString(string $key): string
    {
        $value = $this->string($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Missing required string config value: {$key}");
        }

        return $value;
    }

    /**
     * Returns the resolved configuration value as an integer.
     *
     * Native integers are returned unchanged. Floats and numeric strings are
     * cast to integers. Any other value falls back to the provided default.
     */
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

    /**
     * Returns the resolved configuration value as a boolean.
     *
     * The method uses PHP boolean validation semantics for scalar values and
     * falls back to the provided default for invalid or non-scalar input.
     * Common textual boolean representations such as `"true"` and `"false"`
     * are supported.
     */
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
     * Returns the resolved configuration value when it is an array.
     *
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Sets a configuration value through dot notation.
     *
     * Missing intermediate segments are created as arrays. Existing
     * intermediate non-array values are replaced with arrays before traversal.
     */
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
     * Recursively merges configuration values into the current state.
     *
     * Incoming values override existing ones through `array_replace_recursive`.
     * The `framework.providers` branch is replaced as a whole instead of being
     * merged by indexes.
     *
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
