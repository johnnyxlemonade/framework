<?php

declare(strict_types=1);

if (!function_exists('normalize_decimal_separator')) {
    function normalize_decimal_separator(string|int|float|null $value = null): string
    {
        return str_replace(',', '.', (string) $value);
    }
}

if (!function_exists('human_weight')) {
    function human_weight(int|float $gram, string $decimal = '.'): string
    {
        if ((float) $gram === 0.0) {
            return '0 g';
        }

        $units = ['g', 'kg', 't', 'kt', 'Mt', 'Gt', 'Tt'];
        $divisors = [1, 1000, 1_000_000, 1_000_000_000, 1_000_000_000_000, 1_000_000_000_000_000, 1_000_000_000_000_000_000];

        $exp = 0;
        while ($exp < count($divisors) - 1 && $gram >= $divisors[$exp + 1]) {
            $exp++;
        }

        $amount = $gram / $divisors[$exp];
        $decimals = match ($exp) {
            0 => 0,
            1 => 3,
            default => 6,
        };

        if ($decimals === 0) {
            $formatted = (string) (int) round($amount);
        } else {
            $formatted = number_format($amount, $decimals, $decimal, '');
            $formatted = rtrim(rtrim($formatted, '0'), $decimal);
        }

        return $formatted . ' ' . $units[$exp];
    }
}

if (!function_exists('human_length')) {
    function human_length(int|float $millimeters, string $decimal = ','): string
    {
        if ($millimeters === 0.0) {
            return '0 mm';
        }

        $units = ['mm', 'cm', 'm', 'km', 'Mm', 'Gm'];
        $divisors = [1, 10, 1000, 1_000_000, 1_000_000_000, 1_000_000_000_000];

        $exp = 0;
        while ($exp < count($divisors) - 1 && $millimeters >= $divisors[$exp + 1]) {
            $exp++;
        }

        $amount = $millimeters / $divisors[$exp];
        $decimals = match ($exp) {
            0, 1 => 0,
            2, 3 => 3,
            default => 6,
        };

        if ($decimals === 0) {
            $formatted = (string) (int) round($amount);
        } else {
            $formatted = number_format($amount, $decimals, $decimal, '');
            $formatted = rtrim(rtrim($formatted, '0'), $decimal);
        }

        return $formatted . ' ' . $units[$exp];
    }
}

if (!function_exists('human_volume')) {
    function human_volume(int|float $cubicMm, string $decimal = ','): string
    {
        if ($cubicMm === 0.0) {
            return '0 mm³';
        }

        $units = ['mm³', 'cm³', 'dm³', 'm³', 'km³', 'Ml', 'Gl', 'Tl'];
        $divisors = [
            1,
            1_000,
            1_000_000,
            1_000_000_000,
            1_000_000_000_000,
            1_000_000_000_000_000,
            1_000_000_000_000_000_000,
            1_000_000_000_000_000_000_000,
        ];

        $exp = 0;
        while ($exp < count($divisors) - 1 && $cubicMm >= $divisors[$exp + 1]) {
            $exp++;
        }

        $amount = $cubicMm / $divisors[$exp];
        $decimals = match ($exp) {
            0, 1, 2 => 0,
            3, 4 => 3,
            default => 6,
        };

        if ($decimals === 0) {
            $formatted = (string) (int) round($amount);
        } else {
            $formatted = number_format($amount, $decimals, $decimal, '');
            $formatted = rtrim(rtrim($formatted, '0'), $decimal);
        }

        return $formatted . ' ' . $units[$exp];
    }
}

if (!function_exists('human_filesize')) {
    function human_filesize(string|int|float|null $bytes = null, int $precision = 2): string
    {
        $size = (float) $bytes;
        if ($size === 0.0) {
            return '0 b';
        }

        $units = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $base = 1024;
        $exp = 0;

        while ($size >= $base && $exp < count($units) - 1) {
            $size /= $base;
            $exp++;
        }

        if ($precision === 0) {
            $formatted = (string) (int) round($size);
        } else {
            $formatted = number_format($size, $precision, '.', '');
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted . ' ' . $units[$exp];
    }
}
