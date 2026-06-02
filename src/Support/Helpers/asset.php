<?php

declare(strict_types=1);

use Lemonade\Framework\Support\BaseUrlResolver;

if (!function_exists('asset')) {
    /**
     * @deprecated use explicit DI, ControllerServices, or $helpers in views.
     * In views prefer $helpers->asset().
     */
    function asset(string $path): string
    {
        $baseUrl = service(BaseUrlResolver::class);

        if ($baseUrl instanceof BaseUrlResolver) {
            return $baseUrl->baseUrl($path);
        }

        return '/' . ltrim($path, '/');
    }
}
