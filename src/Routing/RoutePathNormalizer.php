<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

/**
 * Canonical path normalization shared by route registration and matching.
 */
final class RoutePathNormalizer
{
    public static function normalize(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '/' : '/' . $path;
    }
}
