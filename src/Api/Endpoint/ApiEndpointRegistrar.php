<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Routing\Router;

final class ApiEndpointRegistrar
{
    public function __construct(
        private readonly Router $router,
        private readonly ApiEndpointRegistry $registry,
        private readonly ApiRoutePathResolver $pathResolver = new ApiRoutePathResolver(),
    ) {}

    public function registerRoutes(string $prefix): void
    {
        foreach ($this->registry->all() as $endpoint) {
            $this->router
                ->map($endpoint->method(), $this->pathResolver->compose($prefix, $endpoint->path()), $endpoint->handler())
                ->name($endpoint->name());
        }
    }
}
