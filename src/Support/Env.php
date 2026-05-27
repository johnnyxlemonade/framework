<?php

declare(strict_types=1);

namespace Lemonade\Framework\Support;

final class Env
{
    public static function string(string $key, ?string $default = null): ?string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $default;
    }

    public static function float(string $key, float $default): float
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $result ?? $default;
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    public static function list(string $key, array $default = []): array
    {
        $value = self::get($key);

        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items, static fn(string $item): bool => $item !== '');
        $items = array_values(array_unique($items));

        return $items === [] ? $default : $items;
    }

    private static function get(string $key): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }
}
