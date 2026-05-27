<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing\Exception;

use RuntimeException;

final class RouteNotFoundException extends RuntimeException
{
    public static function forRequest(string $method, string $uri): self
    {
        return new self(sprintf(
            'Route not found for [%s] %s',
            strtoupper($method),
            $uri,
        ));
    }

    public static function forName(string $name): self
    {
        return new self(sprintf(
            'Named route "%s" not found.',
            $name,
        ));
    }
}
