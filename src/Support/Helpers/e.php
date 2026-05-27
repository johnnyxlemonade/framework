<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if (is_scalar($value)) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        if ($value instanceof Stringable) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        return '';
    }
}
