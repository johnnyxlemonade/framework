<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

final class ApiEndpoint
{
    /**
     * @param list<string> $tags
     * @param list<string> $scopes
     * @param array<int, int> $successStatusCodes
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $handler,
        private readonly string $name,
        private readonly string $summary,
        private readonly string $description,
        private readonly ApiAccess $access = ApiAccess::Protected,
        private readonly array $tags = [],
        private readonly array $scopes = [],
        private readonly ?string $requestSchema = null,
        private readonly ?string $responseSchema = null,
        private readonly array $successStatusCodes = [200],
    ) {
        $this->assertMethod($method);
        $this->assertPath($path);
        $this->assertName($name);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function handler(): string
    {
        return $this->handler;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function access(): ApiAccess
    {
        return $this->access;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function requestSchema(): ?string
    {
        return $this->requestSchema;
    }

    public function responseSchema(): ?string
    {
        return $this->responseSchema;
    }

    /**
     * @return array<int, int>
     */
    public function successStatusCodes(): array
    {
        return $this->successStatusCodes;
    }

    public function isPublic(): bool
    {
        return $this->access === ApiAccess::Public;
    }

    private function assertMethod(string $method): void
    {
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported API method "%s".', $method));
        }
    }

    private function assertPath(string $path): void
    {
        if ($path === '' || $path[0] !== '/') {
            throw new \InvalidArgumentException(sprintf('API endpoint path "%s" must start with "/".', $path));
        }
    }

    private function assertName(string $name): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('API endpoint name cannot be empty.');
        }
    }
}
