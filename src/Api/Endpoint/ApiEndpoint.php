<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Routing\ControllerAction;

/**
 * Describes one immutable API route, its controller target and its authorization metadata.
 */
final readonly class ApiEndpoint
{
    /**
     * Validates and captures the route contract, defaulting omitted metadata to an empty policy.
     *
     * @param non-empty-string $method
     * @param non-empty-string $path
     * @param non-empty-string $name
     * @param non-empty-string $summary
     * @throws \InvalidArgumentException When the method or required route metadata is invalid.
     */
    public function __construct(
        private string $method,
        private string $path,
        private ControllerAction $controllerAction,
        private string $name,
        private string $summary,
        private string $description,
        private ApiAccess $access = ApiAccess::Protected,
        ?ApiEndpointMetadata $metadata = null,
    ) {
        $this->metadata = $metadata ?? ApiEndpointMetadata::default();

        $this->assertMethod($method);
        $this->assertPath($path);
        $this->assertName($name);
        $this->assertSummary($summary);
    }

    private ApiEndpointMetadata $metadata;

    /**
     * Returns the uppercase HTTP method registered for request matching.
     *
     * @return non-empty-string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns the configured API-relative path beginning with a slash.
     *
     * @return non-empty-string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Returns the controller and action dispatched for a matching request.
     */
    public function controllerAction(): ControllerAction
    {
        return $this->controllerAction;
    }

    /**
     * Returns the unique endpoint name used for route registration and lookup.
     *
     * @return non-empty-string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the non-empty short description exposed in API documentation.
     *
     * @return non-empty-string
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * Returns the detailed description exposed in API documentation.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Returns the authentication boundary enforced by API authorization middleware.
     */
    public function access(): ApiAccess
    {
        return $this->access;
    }

    /**
     * Returns the complete documentation and permission metadata for this endpoint.
     */
    public function metadata(): ApiEndpointMetadata
    {
        return $this->metadata;
    }

    /**
     * Returns documentation tags from the endpoint metadata.
     *
     * @return list<non-empty-string>
     */
    public function tags(): array
    {
        return $this->metadata->tags();
    }

    /**
     * Returns every scope required after the endpoint access boundary is satisfied.
     *
     * @return list<non-empty-string>
     */
    public function scopes(): array
    {
        return $this->metadata->scopes();
    }

    /**
     * Returns OpenAPI parameter definitions without transforming their schemas.
     *
     * @return list<array<string, mixed>>
     */
    public function parameters(): array
    {
        return $this->metadata->parameters();
    }

    /**
     * Returns the optional OpenAPI request body schema.
     *
     * @return array<string, mixed>|null
     */
    public function requestBodySchema(): ?array
    {
        return $this->metadata->requestBodySchema();
    }

    /**
     * Returns the optional OpenAPI success response schema.
     *
     * @return array<string, mixed>|null
     */
    public function responseSchema(): ?array
    {
        return $this->metadata->responseSchema();
    }

    /**
     * Returns the status codes documented as successful responses.
     *
     * @return list<int<100, 599>>
     */
    public function successStatusCodes(): array
    {
        return $this->metadata->successStatusCodes();
    }

    /**
     * Reports whether middleware may dispatch this endpoint without authentication.
     */
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

    private function assertSummary(string $summary): void
    {
        if (trim($summary) === '') {
            throw new \InvalidArgumentException('API endpoint summary cannot be empty.');
        }
    }

}
