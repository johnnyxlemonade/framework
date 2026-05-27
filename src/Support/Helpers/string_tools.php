<?php

declare(strict_types=1);

if (!function_exists('remove_emoji')) {
    function remove_emoji(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $regex = '/[\x{1F100}-\x{1F1FF}'
            . '\x{1F300}-\x{1F5FF}'
            . '\x{1F600}-\x{1F64F}'
            . '\x{1F680}-\x{1F6FF}'
            . '\x{1F900}-\x{1F9FF}'
            . '\x{2600}-\x{26FF}'
            . '\x{2700}-\x{27BF}]/u';

        return trim((string) preg_replace($regex, '', $text));
    }
}
