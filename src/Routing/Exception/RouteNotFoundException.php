<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing\Exception;

use RuntimeException;

/**
 * Represents a failed route lookup without retaining a request query string.
 */
final class RouteNotFoundException extends RuntimeException
{
    /**
     * Creates a diagnostic containing only the request method and URI path.
     */
    public static function forRequest(string $method, string $path): self
    {
        return new self(sprintf(
            'Route not found for [%s] %s',
            strtoupper($method),
            $path,
        ));
    }

    /**
     * Creates a diagnostic for an application-defined route name that is not registered.
     */
    public static function forName(string $name): self
    {
        return new self(sprintf(
            'Named route "%s" not found.',
            $name,
        ));
    }
}
