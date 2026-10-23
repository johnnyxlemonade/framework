<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Routing\Router;

/**
 * Materializes registered API endpoint definitions as named framework routes under one prefix.
 */
final readonly class ApiEndpointRegistrar
{
    /**
     * Binds route registration to the canonical router, endpoint registry and path policy.
     */
    public function __construct(
        private Router $router,
        private ApiEndpointRegistry $registry,
        private ApiRoutePathResolver $pathResolver = new ApiRoutePathResolver(),
    ) {
    }

    /**
     * Registers every current endpoint with its composed path and stable endpoint name.
     */
    public function registerRoutes(string $prefix): void
    {
        foreach ($this->registry->all() as $endpoint) {
            $this->router
                ->map(
                    $endpoint->method(),
                    $this->pathResolver->compose($prefix, $endpoint->path()),
                    $endpoint->controllerAction(),
                )
                ->name($endpoint->name());
        }
    }
}
