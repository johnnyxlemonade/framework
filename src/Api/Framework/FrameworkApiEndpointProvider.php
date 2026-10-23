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

/**
 * Contributes enabled health, OpenAPI and documentation endpoints from resolved framework policy.
 */
final readonly class FrameworkApiEndpointProvider implements ApiEndpointProviderInterface
{
    /**
     * Captures the resolved endpoint routes, access modes and scopes used during registration.
     */
    public function __construct(
        private ApiConfig $config,
    ) {
    }

    /**
     * Registers health independently, then registers documentation endpoints when globally enabled.
     */
    public function register(ApiEndpointRegistry $registry): void
    {
        $framework = $this->config->framework();
        $health = $framework->health();

        if ($health->enabled()) {
            /**
             * @var non-empty-string $healthRoute
             */
            $healthRoute = $health->route();
            $registry->get(
                path: $healthRoute,
                controllerAction: ControllerAction::for(HealthController::class, 'show'),
                name: 'framework.health',
                summary: 'Framework health check',
                description: 'Returns basic framework runtime availability status.',
                access: $health->access(),
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Health'],
                ),
            );
        }

        if (!$framework->enabled()) {
            return;
        }

        $openApi = $framework->openApi();
        if ($openApi->enabled()) {
            /**
             * @var non-empty-string $openApiRoute
             */
            $openApiRoute = $openApi->route();
            $registry->get(
                path: $openApiRoute,
                controllerAction: ControllerAction::for(OpenApiController::class, 'show'),
                name: 'framework.openapi',
                summary: 'OpenAPI specification',
                description: 'Returns generated OpenAPI specification for registered API endpoints.',
                access: $openApi->access(),
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $openApi->scopes(),
                ),
            );
        }

        $docs = $framework->docs();
        if ($docs->enabled()) {
            /**
             * @var non-empty-string $docsRoute
             */
            $docsRoute = $docs->route();
            $registry->get(
                path: $docsRoute,
                controllerAction: ControllerAction::for(DocsController::class, 'show'),
                name: 'framework.docs',
                summary: 'Framework API docs',
                description: 'Returns simple human-readable API documentation.',
                access: $docs->access(),
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $docs->scopes(),
                    responseContentType: 'text/html; charset=utf-8',
                ),
            );
        }
    }
}
