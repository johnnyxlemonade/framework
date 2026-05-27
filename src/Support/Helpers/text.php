<?php

declare(strict_types=1);

if (!function_exists('excerpt')) {
    function excerpt(string|null $value = null, int $length = 168, bool $appendEllipsis = true): string
    {
        if ($value === null) {
            return '';
        }

        $clean = trim(strip_tags(html_entity_decode($value, ENT_QUOTES, 'UTF-8')));
        $short = trim(mb_substr($clean, 0, $length, 'UTF-8'));
        $short = (string) preg_replace('/\s\s+/', ' ', $short);

        if ($appendEllipsis && mb_strlen($clean, 'UTF-8') > $length) {
            $short .= '...';
        }

        return $short;
    }
}

if (!function_exists('trim_invisible_string')) {
    function trim_invisible_string(string|null $value = null, string|null $what = null, string $replacement = ' '): string
    {
        $what ??= '\x00-\x20';

        $processed = preg_replace(
            pattern: '/[' . $what . ']+/',
            replacement: $replacement,
            subject: (string) $value,
        );

        return trim((string) $processed, $what);
    }
}

if (!function_exists('strip_invalid_xml')) {
    function strip_invalid_xml(string|null $value = null): string
    {
        if ($value === null) {
            return '';
        }

        $ret = '';
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $current = ord($value[$i]);

            if (
                $current === 0x9
                || $current === 0xA
                || $current === 0xD
                || $current >= 0x20
            ) {
                $ret .= chr($current);
            } else {
                $ret .= ' ';
            }
        }

        return $ret;
    }
}
