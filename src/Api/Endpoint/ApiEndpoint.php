<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

final class ApiEndpoint
{
    /**
     * @param non-empty-string $method
     * @param non-empty-string $path
     * @param non-empty-string $handler
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @param non-empty-string $summary
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $handler,
        private readonly string $name,
        private readonly string $summary,
        private readonly string $description,
        private readonly ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ) {
        $this->metadata = $metadata ?? ApiEndpointMetadata::default();

        $this->assertMethod($method);
        $this->assertPath($path);
        $this->assertHandler($handler);
        $this->assertName($name);
        $this->assertSummary($summary);
    }

    private readonly ApiEndpointMetadata $metadata;

    /**
     * @return non-empty-string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * @return non-empty-string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return non-empty-string
     */
    public function handler(): string
    {
        return $this->handler;
    }

    /**
     * @return non-empty-string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     */
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

    public function metadata(): ApiEndpointMetadata
    {
        return $this->metadata;
    }

    /**
     * @return list<non-empty-string>
     */
    public function tags(): array
    {
        return $this->metadata->tags();
    }

    /**
     * @return list<non-empty-string>
     */
    public function scopes(): array
    {
        return $this->metadata->scopes();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parameters(): array
    {
        return $this->metadata->parameters();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function requestBodySchema(): ?array
    {
        return $this->metadata->requestBodySchema();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function responseSchema(): ?array
    {
        return $this->metadata->responseSchema();
    }

    /**
     * @return list<int<100, 599>>
     */
    public function successStatusCodes(): array
    {
        return $this->metadata->successStatusCodes();
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

    private function assertHandler(string $handler): void
    {
        if (trim($handler) === '') {
            throw new \InvalidArgumentException('API endpoint handler cannot be empty.');
        }
    }

    private function assertSummary(string $summary): void
    {
        if (trim($summary) === '') {
            throw new \InvalidArgumentException('API endpoint summary cannot be empty.');
        }
    }

}
