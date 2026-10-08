<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

/**
 * Defines request attributes exchanged at the routing dispatch boundary.
 *
 * A dispatch path can affect matching without replacing the externally visible
 * request URI; a successful match is then attached to that same request.
 */
final class RouteRequestAttributes
{
    /**
     * Contains the immutable RouteMatch for a successfully matched request.
     */
    public const MATCH = 'lemonade.route_match';

    /**
     * Contains an internal path override used only while matching a route.
     *
     * The original request URI remains the source of request data exposed to
     * middleware and controllers.
     */
    public const DISPATCH_PATH = 'lemonade.route_dispatch_path';

    /**
     * Prevents instantiation of the attribute-name namespace.
     */
    private function __construct()
    {
    }
}
