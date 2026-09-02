<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

final class ApiRoutePathResolver
{
    public function normalizePrefix(string $prefix): string
    {
        $prefix = '/' . trim($prefix, '/');

        return $prefix === '/' ? '' : rtrim($prefix, '/');
    }

    public function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    public function compose(string $prefix, string $path): string
    {
        return $this->normalizePath(
            $this->normalizePrefix($prefix) . '/' . ltrim($path, '/'),
        );
    }
}
