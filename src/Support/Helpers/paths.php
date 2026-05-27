<?php

declare(strict_types=1);

use Lemonade\Framework\Core\Context\ApplicationContext;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $context = service(ApplicationContext::class);

        if (!$context instanceof ApplicationContext) {
            return $path;
        }

        return $context->path($path);
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        $context = service(ApplicationContext::class);

        if (!$context instanceof ApplicationContext) {
            return $path;
        }

        return $context->appPath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $context = service(ApplicationContext::class);

        if (!$context instanceof ApplicationContext) {
            return $path;
        }

        return $context->storagePath($path);
    }
}
