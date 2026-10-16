<?php

declare(strict_types=1);

namespace Lemonade\Framework\Core\Config\Internal;

/**
 * Canonicalizes primitive values consumed by internal configuration resolvers.
 *
 * @internal
 */
final class ConfigValueNormalizer
{
    private function __construct()
    {
    }

    /**
     * Converts scalar input to text without trimming or replacing an empty result.
     */
    public static function scalarStringOr(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Returns trimmed scalar text or the fallback when the input is structured or blank.
     */
    public static function nonEmptyStringOr(mixed $value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? $default : $normalized;
    }

    /**
     * Returns trimmed scalar text while treating null, structured, and blank input as absent.
     */
    public static function nullableString(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Converts integers, floats, and numeric strings to an integer or returns the fallback.
     */
    public static function intOr(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Converts integers, floats, and numeric strings to an integer while treating other input as absent.
     */
    public static function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Converts integers, floats, and numeric strings to a float or returns the fallback.
     */
    public static function floatOr(mixed $value, float $default): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * Parses scalar input with PHP's validated boolean syntax or returns the fallback.
     */
    public static function boolOr(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $default;
    }

    /**
     * Retains string-keyed entries from an array and treats non-array input as an empty mapping.
     *
     * @return array<string, mixed>
     */
    public static function assoc(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    /**
     * Returns unique trimmed non-empty scalar strings in their first-seen order.
     *
     * @return list<non-empty-string>
     */
    public static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $string = trim((string) $item);

            if ($string === '' || in_array($string, $normalized, true)) {
                continue;
            }

            $normalized[] = $string;
        }

        return $normalized;
    }
}
