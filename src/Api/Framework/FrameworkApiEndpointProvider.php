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

final class FrameworkApiEndpointProvider implements ApiEndpointProviderInterface
{
    public function __construct(
        private readonly ApiConfig $config,
    ) {}

    public function register(ApiEndpointRegistry $registry): void
    {
        if ($this->config->framework->health->enabled) {
            /** @var non-empty-string $healthRoute */
            $healthRoute = $this->config->framework->health->route;
            $registry->get(
                path: $healthRoute,
                handler: HealthController::class . '@show',
                name: 'framework.health',
                summary: 'Framework health check',
                description: 'Returns basic framework runtime availability status.',
                access: $this->config->framework->health->access,
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Health'],
                ),
            );
        }

        if (!$this->config->framework->enabled) {
            return;
        }

        if ($this->config->framework->openapi->enabled) {
            /** @var non-empty-string $openApiRoute */
            $openApiRoute = $this->config->framework->openapi->route;
            $registry->get(
                path: $openApiRoute,
                handler: OpenApiController::class . '@show',
                name: 'framework.openapi',
                summary: 'OpenAPI specification',
                description: 'Returns generated OpenAPI specification for registered API endpoints.',
                access: $this->config->framework->openapi->access,
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $this->config->framework->openapi->scopes,
                ),
            );
        }

        if ($this->config->framework->docs->enabled) {
            /** @var non-empty-string $docsRoute */
            $docsRoute = $this->config->framework->docs->route;
            $registry->get(
                path: $docsRoute,
                handler: DocsController::class . '@show',
                name: 'framework.docs',
                summary: 'Framework API docs',
                description: 'Returns simple human-readable API documentation.',
                access: $this->config->framework->docs->access,
                metadata: new ApiEndpointMetadata(
                    tags: ['Framework', 'Documentation'],
                    scopes: $this->config->framework->docs->scopes,
                    responseContentType: 'text/html; charset=utf-8',
                ),
            );
        }
    }
}
