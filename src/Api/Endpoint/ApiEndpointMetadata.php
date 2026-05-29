<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Endpoint;

final class ApiEndpointMetadata
{
    /**
     * @param list<non-empty-string> $tags
     * @param list<non-empty-string> $scopes
     * @param list<array<string, mixed>> $parameters
     * @param array<string, mixed>|null $requestBodySchema
     * @param array<string, mixed>|null $responseSchema
     * @param list<int<100, 599>> $successStatusCodes
     * @param non-empty-string $responseContentType
     */
    public function __construct(
        private readonly array $tags = [],
        private readonly array $scopes = [],
        private readonly array $parameters = [],
        private readonly ?array $requestBodySchema = null,
        private readonly ?array $responseSchema = null,
        private readonly array $successStatusCodes = [200],
        private readonly string $responseContentType = 'application/json',
    ) {
        $this->assertTags($tags);
        $this->assertScopes($scopes);
        $this->assertSuccessStatusCodes($successStatusCodes);
        $this->assertResponseContentType($responseContentType);
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * @return list<non-empty-string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * @return list<non-empty-string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function requestBodySchema(): ?array
    {
        return $this->requestBodySchema;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function responseSchema(): ?array
    {
        return $this->responseSchema;
    }

    /**
     * @return list<int<100, 599>>
     */
    public function successStatusCodes(): array
    {
        return $this->successStatusCodes;
    }

    /**
     * @return non-empty-string
     */
    public function responseContentType(): string
    {
        return $this->responseContentType;
    }

    /**
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
