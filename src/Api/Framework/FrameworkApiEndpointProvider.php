<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Framework;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Documentation\DocsController;
use Lemonade\Framework\Api\Documentation\OpenApiController;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Framework\Health\HealthController;
use Lemonade\Framework\Routing\ControllerAction;

final class FrameworkApiEndpointProvider implements ApiEndpointProviderInterface
{
    public function __construct(
        private readonly ApiConfig $config,
    ) {
    }

    public function register(ApiEndpointRegistry $registry): void
    {
        $framework = $this->config->framework();

        if ($framework->health->enabled) {
            /** @var non-empty-string $healthRoute */
            $healthRoute = $framework->health->route;
            $registry->get(
                path: $healthRoute,
                controllerAction: ControllerAction::for(HealthController::class, 'show'),
                name: 'framework.health',
                summary: 'Framework health check',
                description: 'Returns basic framework runtime availability status.',
                access: $framework->health->access,
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Health'],
                ),
            );
        }

        if (!$framework->enabled) {
            return;
        }

        if ($framework->openapi->enabled) {
            /** @var non-empty-string $openApiRoute */
            $openApiRoute = $framework->openapi->route;
            $registry->get(
                path: $openApiRoute,
                controllerAction: ControllerAction::for(OpenApiController::class, 'show'),
                name: 'framework.openapi',
                summary: 'OpenAPI specification',
                description: 'Returns generated OpenAPI specification for registered API endpoints.',
                access: $framework->openapi->access,
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $framework->openapi->scopes,
                ),
            );
        }

        if ($framework->docs->enabled) {
            /** @var non-empty-string $docsRoute */
            $docsRoute = $framework->docs->route;
            $registry->get(
                path: $docsRoute,
                controllerAction: ControllerAction::for(DocsController::class, 'show'),
                name: 'framework.docs',
                summary: 'Framework API docs',
                description: 'Returns simple human-readable API documentation.',
                access: $framework->docs->access,
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $framework->docs->scopes,
                    responseContentType: 'text/html; charset=utf-8',
                ),
            );
        }
    }
}
