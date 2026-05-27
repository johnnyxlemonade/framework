<?php

declare(strict_types=1);

if (!function_exists('escape_html')) {
    /**
     * @param array<string, mixed>|string $value
     * @return array<string, mixed>|string
     */
    function escape_html(array|string $value, bool $doubleEncode = true): array|string
    {
        if (is_array($value)) {
            $escaped = [];

            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $nested = [];
                    foreach ($item as $nestedKey => $nestedValue) {
                        if (is_string($nestedKey)) {
                            $nested[$nestedKey] = $nestedValue;
                        }
                    }

                    $escaped[$key] = escape_html($nested, $doubleEncode);
                    continue;
                }

                if (is_scalar($item) || $item instanceof Stringable) {
                    $escaped[$key] = escape_html((string) $item, $doubleEncode);
                }
            }

            return $escaped;
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('escape_js')) {
    function escape_js(string|null $value = null): string
    {
        return addcslashes((string) $value, "\"'\\\0..\037/");
    }
}
