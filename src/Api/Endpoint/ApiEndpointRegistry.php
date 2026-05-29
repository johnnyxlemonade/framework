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
     * @param list<string> $tags
     * @param list<string> $scopes
     * @param array<int, int> $successStatusCodes
     */
    public function add(
        string $method,
        string $path,
        string $handler,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        array $tags = [],
        array $scopes = [],
        ?string $requestSchema = null,
        ?string $responseSchema = null,
        array $successStatusCodes = [200],
    ): ApiEndpoint {
        $endpoint = new ApiEndpoint(
            method: strtoupper($method),
            path: $this->normalizePath($path),
            handler: $handler,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            tags: $tags,
            scopes: $scopes,
            requestSchema: $requestSchema,
            responseSchema: $responseSchema,
            successStatusCodes: $successStatusCodes,
        );

        $this->register($endpoint);

        return $endpoint;
    }

    /**
     * @param list<string> $tags
     * @param list<string> $scopes
     */
    public function get(
        string $path,
        string $handler,
        string $name,
        string $summary,
        string $description,
        ApiAccess $access = ApiAccess::Protected,
        array $tags = [],
        array $scopes = [],
        ?string $responseSchema = null,
    ): ApiEndpoint {
        return $this->add(
            method: 'GET',
            path: $path,
            handler: $handler,
            name: $name,
            summary: $summary,
            description: $description,
            access: $access,
            tags: $tags,
            scopes: $scopes,
            responseSchema: $responseSchema,
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
        return strtoupper($method) . ' ' . $this->normalizePath($path);
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
