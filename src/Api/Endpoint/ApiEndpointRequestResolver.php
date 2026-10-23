<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\RouteMatch;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRequestAttributes;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Binds canonical router matches to registered API endpoint security metadata.
 *
 * A route resolves only when its stable name, method, template and controller target agree with
 * the endpoint registry; URI text alone never establishes an authorization policy.
 */
final readonly class ApiEndpointRequestResolver
{
    /**
     * Uses the shared Router as the only source of request matching decisions.
     */
    public function __construct(
        private ApiEndpointRegistry $endpoints,
        private ApiConfig $config,
        private Router $router,
        private ApiRoutePathResolver $pathResolver = new ApiRoutePathResolver(),
    ) {
    }

    /**
     * Resolves endpoint policy from the canonical router match, never from an independent path matcher.
     */
    public function resolve(ServerRequestInterface $request): ?ApiEndpoint
    {
        $match = $request->getAttribute(RouteRequestAttributes::MATCH);

        if (!$match instanceof RouteMatch) {
            $dispatchPath = $request->getAttribute(RouteRequestAttributes::DISPATCH_PATH);

            try {
                $match = $this->router->match(
                    $request,
                    is_string($dispatchPath) ? $dispatchPath : null,
                );
            } catch (RouteNotFoundException) {
                return null;
            }
        }

        return $this->resolveMatch($match);
    }

    /**
     * Returns endpoint policy only when the route name, method, path and controller binding all agree.
     */
    public function resolveMatch(RouteMatch $match): ?ApiEndpoint
    {
        $name = $match->name();
        if ($name === null) {
            return null;
        }

        $endpoint = $this->endpoints->findByName($name);
        if ($endpoint === null) {
            return null;
        }

        $expectedPath = $this->pathResolver->compose(
            $this->config->prefix(),
            $endpoint->path(),
        );

        if ($match->method() !== $endpoint->method() || $match->path() !== $expectedPath) {
            return null;
        }

        $matchedAction = $match->controllerAction();
        $endpointAction = $endpoint->controllerAction();

        if (
            $matchedAction->controllerClass() !== $endpointAction->controllerClass()
            || $matchedAction->method() !== $endpointAction->method()
        ) {
            return null;
        }

        return $endpoint;
    }

    /**
     * Reports whether the request's effective dispatch path lies below the configured API prefix.
     */
    public function isApiRequest(ServerRequestInterface $request): bool
    {
        $dispatchPath = $request->getAttribute(RouteRequestAttributes::DISPATCH_PATH);
        $path = is_string($dispatchPath) ? $dispatchPath : $request->getUri()->getPath();

        return $this->pathResolver->resolveRequestPath(
            prefix: $this->config->prefix(),
            requestPath: $path,
        ) !== null;
    }
}
