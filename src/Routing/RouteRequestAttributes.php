<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

/**
 * Public request attribute names populated by the routing dispatch boundary.
 */
final class RouteRequestAttributes
{
    /**
     * Contains the immutable RouteMatch for a successfully matched request.
     */
    public const MATCH = 'lemonade.route_match';

    private function __construct()
    {
    }
}
