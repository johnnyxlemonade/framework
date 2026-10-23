<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

use Lemonade\Framework\Http\HttpStatus;

/**
 * Holds validated authorization and OpenAPI details shared by endpoint registration and generation.
 */
final readonly class ApiEndpointMetadata
{
    /**
     * Validates and captures immutable endpoint metadata.
     *
     * @param list<non-empty-string> $tags
     * @param list<non-empty-string> $scopes
     * @param list<array<string, mixed>> $parameters
     * @param array<string, mixed>|null $requestBodySchema
     * @param array<string, mixed>|null $responseSchema
     * @param list<int<100, 599>> $successStatusCodes
     * @param non-empty-string $responseContentType
     * @throws \InvalidArgumentException When a required metadata value is empty or invalid.
     */
    public function __construct(
        private array $tags = [],
        private array $scopes = [],
        private array $parameters = [],
        private ?array $requestBodySchema = null,
        private ?array $responseSchema = null,
        private array $successStatusCodes = [HttpStatus::OK->value],
        private string $responseContentType = 'application/json',
    ) {
        $this->assertTags($tags);
        $this->assertScopes($scopes);
        $this->assertSuccessStatusCodes($successStatusCodes);
        $this->assertResponseContentType($responseContentType);
    }

    /**
     * Creates metadata with no tags, scopes or schemas and a JSON 200 success response.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Returns the non-empty documentation tags in declaration order.
     *
     * @return list<non-empty-string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Returns every scope required to authorize the endpoint.
     *
     * @return list<non-empty-string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * Returns OpenAPI parameter definitions without schema normalization.
     *
     * @return list<array<string, mixed>>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Returns the optional OpenAPI request body schema.
     *
     * @return array<string, mixed>|null
     */
    public function requestBodySchema(): ?array
    {
        return $this->requestBodySchema;
    }

    /**
     * Returns the optional schema wrapped by successful API responses.
     *
     * @return array<string, mixed>|null
     */
    public function responseSchema(): ?array
    {
        return $this->responseSchema;
    }

    /**
     * Returns the HTTP status codes documented as successful responses.
     *
     * @return list<int<100, 599>>
     */
    public function successStatusCodes(): array
    {
        return $this->successStatusCodes;
    }

    /**
     * Returns the media type used to document successful response content.
     *
     * @return non-empty-string
     */
    public function responseContentType(): string
    {
        return $this->responseContentType;
    }

    /**
     * Rejects empty documentation tags.
     *
     * @param list<non-empty-string> $tags
     */
    private function assertTags(array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag === '') {
                throw new \InvalidArgumentException('API endpoint tag must not be empty.');
            }
        }
    }

    /**
     * Rejects empty authorization scopes.
     *
     * @param list<non-empty-string> $scopes
     */
    private function assertScopes(array $scopes): void
    {
        foreach ($scopes as $scope) {
            if ($scope === '') {
                throw new \InvalidArgumentException('API endpoint scope must not be empty.');
            }
        }
    }

    /**
     * Requires at least one status code within the valid HTTP range.
     *
     * @param list<int<100, 599>> $successStatusCodes
     */
    private function assertSuccessStatusCodes(array $successStatusCodes): void
    {
        if ($successStatusCodes === []) {
            throw new \InvalidArgumentException('API endpoint success status codes cannot be empty.');
        }

        foreach ($successStatusCodes as $statusCode) {
            if ($statusCode < 100 || $statusCode > 599) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid HTTP status code "%d".',
                    $statusCode,
                ));
            }
        }
    }

    private function assertResponseContentType(string $responseContentType): void
    {
        if (trim($responseContentType) === '') {
            throw new \InvalidArgumentException('API endpoint response content type must not be empty.');
        }
    }
}
