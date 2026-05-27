<?php

declare(strict_types=1);

if (!function_exists('server_value')) {
    function server_value(string $key): string
    {
        $value = $_SERVER[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}

if (!function_exists('supports_webp')) {
    function supports_webp(): bool
    {
        return str_contains(
            strtolower(server_value('HTTP_ACCEPT')),
            'image/webp',
        );
    }
}
