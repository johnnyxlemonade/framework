<?php

declare(strict_types=1);

if (!function_exists('number_to_directory')) {
    function number_to_directory(string|int|null $numberId = null, bool $trimTrailingSlash = false): string
    {
        $hex = str_pad(
            dechex((int) ($numberId ?? 0)),
            8,
            '0',
            STR_PAD_LEFT,
        );

        $directory = chunk_split($hex, 2, '/');

        return $trimTrailingSlash ? rtrim($directory, '/') : $directory;
    }
}
