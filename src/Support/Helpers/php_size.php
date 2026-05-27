<?php

declare(strict_types=1);

if (!function_exists('php_size_to_mb')) {
    /**
     * Converts php.ini size value (for example 128M, 2G, 1024) into MB.
     */
    function php_size_to_mb(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^(\d+)\s*([tgmk]?)$/i', $value, $matches) !== 1) {
            return 0;
        }

        $num = (int) $matches[1];
        $unit = strtolower($matches[2]);

        if ($num <= 0) {
            return 0;
        }

        return match ($unit) {
            't' => min($num * 1024 * 1024, PHP_INT_MAX),
            'g' => min($num * 1024, PHP_INT_MAX),
            'm' => $num,
            'k' => (int) ceil($num / 1024),
            default => (int) floor($num / 1024 / 1024),
        };
    }
}
