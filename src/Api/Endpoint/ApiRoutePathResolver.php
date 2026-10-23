<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

/**
 * Applies the shared normalization rules used for API prefixes, routes and request matching.
 */
final readonly class ApiRoutePathResolver
{
    /**
     * Converts a root prefix to an empty prefix and removes surrounding or trailing slashes otherwise.
     */
    public function normalizePrefix(string $prefix): string
    {
        $prefix = '/' . trim($prefix, '/');

        return $prefix === '/' ? '' : rtrim($prefix, '/');
    }

    /**
     * Produces an absolute normalized path while preserving the root path.
     */
    public function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    /**
     * Joins an API prefix and endpoint-relative path using canonical slash rules.
     */
    public function compose(string $prefix, string $path): string
    {
        return $this->normalizePath(
            $this->normalizePrefix($prefix) . '/' . ltrim($path, '/'),
        );
    }

    /**
     * Removes the matching API prefix, returning null when the request lies outside that boundary.
     */
    public function resolveRequestPath(string $prefix, string $requestPath): ?string
    {
        $normalizedPath = $this->normalizeDecodedPath($requestPath);
        $normalizedPrefix = $this->normalizeDecodedPrefix($prefix);

        if ($normalizedPrefix === '') {
            return $normalizedPath;
        }

        if ($normalizedPath === $normalizedPrefix) {
            return '/';
        }

        if (!str_starts_with($normalizedPath, $normalizedPrefix . '/')) {
            return null;
        }

        return $this->normalizePath(substr($normalizedPath, strlen($normalizedPrefix)));
    }

    private function normalizeDecodedPath(string $path): string
    {
        return $this->normalizePath(implode('/', array_map(
            static fn(string $segment): string => rawurldecode($segment),
            explode('/', trim($path, '/')),
        )));
    }

    private function normalizeDecodedPrefix(string $prefix): string
    {
        $normalized = $this->normalizeDecodedPath($prefix);

        return $normalized === '/' ? '' : rtrim($normalized, '/');
    }
}
