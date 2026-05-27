<?php

declare(strict_types=1);

if (!function_exists('parse_textarea_list')) {
    /**
     * @return list<string>
     */
    function parse_textarea_list(?string $input = null): array
    {
        $raw = trim((string) $input);

        if ($raw === '') {
            return [];
        }

        $split = preg_split('/\r\n|\r|\n/', $raw);
        $items = array_map('trim', is_array($split) ? $split : []);
        $items = array_values(array_filter($items, static fn(string $line): bool => $line !== ''));

        return $items;
    }
}

if (!function_exists('normalize_url')) {
    function normalize_url(?string $url = null): string
    {
        $trimmed = trim((string) $url);

        return match (true) {
            $trimmed === '' => '#',
            str_starts_with($trimmed, 'http://') => $trimmed,
            str_starts_with($trimmed, 'https://') => $trimmed,
            str_starts_with($trimmed, '/') => $trimmed,
            default => '/' . $trimmed,
        };
    }
}

if (!function_exists('apply_rounding')) {
    function apply_rounding(int|float|string|null $value, string $type): float
    {
        if ($value === null || !is_numeric($value)) {
            return 0.0;
        }

        $numeric = (float) $value;

        return match ($type) {
            'math2one' => round($numeric, 0),
            'math2half' => round($numeric * 2) / 2,
            'math2tenth' => round($numeric, 1),
            'math5cent' => round($numeric * 20) / 20,
            'up2one' => ceil($numeric),
            'up2half' => ceil($numeric * 2) / 2,
            'up2tenth' => ceil($numeric * 10) / 10,
            'down2one' => floor($numeric),
            'down2half' => floor($numeric * 2) / 2,
            'down2tenth' => floor($numeric * 10) / 10,
            default => $numeric,
        };
    }
}
