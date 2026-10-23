<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Api\Config\ApiConfig;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Maps an incoming request under the configured API prefix to canonical endpoint metadata.
 */
final readonly class ApiEndpointRequestResolver
{
    /**
     * Binds request matching to the resolved API prefix and endpoint registry.
     */
    public function __construct(
        private ApiEndpointRegistry $endpoints,
        private ApiConfig $config,
        private ApiRoutePathResolver $pathResolver = new ApiRoutePathResolver(),
    ) {
    }

    /**
     * Returns the matching endpoint, or null for paths outside the API prefix or registry.
     */
    public function resolve(ServerRequestInterface $request): ?ApiEndpoint
    {
        $path = $this->pathResolver->resolveRequestPath(
            prefix: $this->config->prefix(),
            requestPath: $request->getUri()->getPath(),
        );

        if ($path === null) {
            return null;
        }

        return $this->endpoints->findByRequest(
            method: $request->getMethod(),
            path: $path,
        );
    }
}
