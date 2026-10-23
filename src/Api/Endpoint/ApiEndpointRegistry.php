<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Routing\ControllerAction;

/**
 * Owns the canonical ordered API endpoint set and enforces unique names and method-path pairs.
 */
final class ApiEndpointRegistry
{
    /**
     * Indexes endpoints by their unique public name.
     *
     * @var array<string, ApiEndpoint>
     */
    private array $endpointsByName = [];

    /**
     * Indexes endpoints by normalized method and path.
     *
     * @var array<string, ApiEndpoint>
     */
    private array $endpointsByMethodAndPath = [];

    /**
     * Normalizes, validates and registers one endpoint.
     *
     * @param non-empty-string $method
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @throws \InvalidArgumentException When endpoint values violate their invariants.
     * @throws \LogicException When its name or normalized method-path pair is already registered.
     */
    public function add(
        string $method,
        string $path,
        ControllerAction $controllerAction,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        $normalizedMethod = $this->normalizeMethod($method);
        $normalizedPath = $this->normalizePath($path);

        $endpoint = new ApiEndpoint(
            method: $normalizedMethod,
            path: $normalizedPath,
            controllerAction: $controllerAction,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );

        $this->register($endpoint);

        return $endpoint;
    }

    /**
     * Registers a GET endpoint with the supplied access and metadata policy.
     *
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @throws \InvalidArgumentException When endpoint values violate their invariants.
     * @throws \LogicException When its name or normalized method-path pair is already registered.
     */
    public function get(
        string $path,
        ControllerAction $controllerAction,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'GET',
            path: $path,
            controllerAction: $controllerAction,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * Registers a POST endpoint with the supplied access and metadata policy.
     *
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @throws \InvalidArgumentException When endpoint values violate their invariants.
     * @throws \LogicException When its name or normalized method-path pair is already registered.
     */
    public function post(
        string $path,
        ControllerAction $controllerAction,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'POST',
            path: $path,
            controllerAction: $controllerAction,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * Registers a PUT endpoint with the supplied access and metadata policy.
     *
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @throws \InvalidArgumentException When endpoint values violate their invariants.
     * @throws \LogicException When its name or normalized method-path pair is already registered.
     */
    public function put(
        string $path,
        ControllerAction $controllerAction,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'PUT',
            path: $path,
            controllerAction: $controllerAction,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * Registers a PATCH endpoint with the supplied access and metadata policy.
     *
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @throws \InvalidArgumentException When endpoint values violate their invariants.
     * @throws \LogicException When its name or normalized method-path pair is already registered.
     */
    public function patch(
        string $path,
        ControllerAction $controllerAction,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'PATCH',
            path: $path,
            controllerAction: $controllerAction,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * Registers a DELETE endpoint with the supplied access and metadata policy.
     *
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @throws \InvalidArgumentException When endpoint values violate their invariants.
     * @throws \LogicException When its name or normalized method-path pair is already registered.
     */
    public function delete(
        string $path,
        ControllerAction $controllerAction,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'DELETE',
            path: $path,
            controllerAction: $controllerAction,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * Returns the endpoint registered under the exact name, or null when none exists.
     */
    public function findByName(string $name): ?ApiEndpoint
    {
        return $this->endpointsByName[$name] ?? null;
    }

    /**
     * Resolves a normalized method-path pair, with HEAD falling back to a matching GET endpoint.
     */
    public function findByRequest(string $method, string $path): ?ApiEndpoint
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        return $this->endpointsByMethodAndPath[$this->key($method, $path)]
            ?? ($method === 'HEAD' ? $this->endpointsByMethodAndPath[$this->key('GET', $path)] ?? null : null);
    }

    /**
     * Returns endpoints in their registration order.
     *
     * @return list<ApiEndpoint>
     */
    public function all(): array
    {
        return array_values($this->endpointsByName);
    }

    private function register(ApiEndpoint $endpoint): void
    {
        if (isset($this->endpointsByName[$endpoint->name()])) {
            throw new \LogicException(sprintf('API endpoint "%s" is already registered.', $endpoint->name()));
        }

        $methodPathKey = $this->key($endpoint->method(), $endpoint->path());

        if (isset($this->endpointsByMethodAndPath[$methodPathKey])) {
            throw new \LogicException(sprintf(
                'API endpoint "%s %s" is already registered.',
                $endpoint->method(),
                $endpoint->path(),
            ));
        }

        $this->endpointsByName[$endpoint->name()] = $endpoint;
        $this->endpointsByMethodAndPath[$methodPathKey] = $endpoint;
    }

    private function key(string $method, string $path): string
    {
        return $this->normalizeMethod($method) . ' ' . $this->normalizePath($path);
    }

    /**
     * Produces a non-empty uppercase method, defaulting empty input to GET.
     *
     * @return non-empty-string
     */
    private function normalizeMethod(string $method): string
    {
        $normalized = strtoupper(trim($method));

        return $normalized !== '' ? $normalized : 'GET';
    }

    /**
     * Produces a non-empty absolute path without a trailing slash except at the root.
     *
     * @return non-empty-string
     */
    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '/';
        }

        $normalized = rtrim($path, '/');

        return $normalized !== '' ? $normalized : '/';
    }
}
