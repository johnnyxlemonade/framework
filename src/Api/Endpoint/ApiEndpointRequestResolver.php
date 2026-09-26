<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Api\Config\ApiConfig;
use Psr\Http\Message\ServerRequestInterface;

final class ApiEndpointRequestResolver
{
    public function __construct(
        private readonly ApiEndpointRegistry $endpoints,
        private readonly ApiConfig $config,
        private readonly ApiRoutePathResolver $pathResolver = new ApiRoutePathResolver(),
    ) {}

    public function resolve(ServerRequestInterface $request): ?ApiEndpoint
    {
        $path = $this->pathResolver->resolveRequestPath(
            prefix: $this->config->prefix,
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
