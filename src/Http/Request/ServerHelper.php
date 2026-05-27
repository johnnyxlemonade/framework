<?php

declare(strict_types=1);

namespace Lemonade\Framework\Http\Request;

final class ServerHelper
{
    public static function get(string $key, string $default = ''): string
    {
        $value = $_SERVER[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SERVER[$key])
            && is_string($_SERVER[$key])
            && $_SERVER[$key] !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $normalized = [];

        foreach ($_SERVER as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
