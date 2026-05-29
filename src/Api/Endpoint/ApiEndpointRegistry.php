<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

final class ApiEndpointRegistry
{
    /**
     * @var array<string, ApiEndpoint>
     */
    private array $endpointsByName = [];

    /**
     * @var array<string, ApiEndpoint>
     */
    private array $endpointsByMethodAndPath = [];

    /**
     * @param non-empty-string $method
     * @param non-empty-string $path
     * @param non-empty-string $handler
     * @param non-empty-string $name
     * @param non-empty-string $summary
     */
    public function add(
        string $method,
        string $path,
        string $handler,
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
            handler: $handler,
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
     * @param non-empty-string $path
     * @param non-empty-string $handler
     * @param non-empty-string $name
     * @param non-empty-string $summary
     */
    public function get(
        string $path,
        string $handler,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'GET',
            path: $path,
            handler: $handler,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $handler
     * @param non-empty-string $name
     * @param non-empty-string $summary
     */
    public function post(
        string $path,
        string $handler,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'POST',
            path: $path,
            handler: $handler,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $handler
     * @param non-empty-string $name
     * @param non-empty-string $summary
     */
    public function put(
        string $path,
        string $handler,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'PUT',
            path: $path,
            handler: $handler,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $handler
     * @param non-empty-string $name
     * @param non-empty-string $summary
     */
    public function patch(
        string $path,
        string $handler,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'PATCH',
            path: $path,
            handler: $handler,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    /**
     * @param non-empty-string $path
     * @param non-empty-string $handler
     * @param non-empty-string $name
     * @param non-empty-string $summary
     */
    public function delete(
        string $path,
        string $handler,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'DELETE',
            path: $path,
            handler: $handler,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            metadata: $metadata,
        );
    }

    public function findByName(string $name): ?ApiEndpoint
    {
        return $this->endpointsByName[$name] ?? null;
    }

    public function findByRequest(string $method, string $path): ?ApiEndpoint
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        return $this->endpointsByMethodAndPath[$this->key($method, $path)]
            ?? ($method === 'HEAD' ? $this->endpointsByMethodAndPath[$this->key('GET', $path)] ?? null : null);
    }

    /**
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
     * @return non-empty-string
     */
    private function normalizeMethod(string $method): string
    {
        $normalized = strtoupper(trim($method));

        return $normalized !== '' ? $normalized : 'GET';
    }

    /**
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
