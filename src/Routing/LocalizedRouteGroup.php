<?php

declare(strict_types=1);

namespace Lemonade\Framework\Routing;

final class LocalizedRouteGroup extends RouteGroup
{
    private readonly RouteGroup $plainGroup;
    private readonly RouteGroup $localizedGroup;

    /**
     * @param array<int, Route> $plainRoutes
     * @param array<int, Route> $localizedRoutes
     */
    public function __construct(
        array $plainRoutes,
        array $localizedRoutes,
    ) {
        $this->plainGroup = new RouteGroup($plainRoutes);
        $this->localizedGroup = new RouteGroup($localizedRoutes);

        parent::__construct([
            ...$plainRoutes,
            ...$localizedRoutes,
        ]);
    }

    public function plain(): RouteGroup
    {
        return $this->plainGroup;
    }

    public function localized(): RouteGroup
    {
        return $this->localizedGroup;
    }
}
