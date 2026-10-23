<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Documentation;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpoint;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Core\Config\AppConfig;
use Lemonade\Framework\Core\FrameworkInfo;
use Lemonade\Framework\Http\HttpStatus;

/**
 * Projects the canonical endpoint registry and runtime configuration into an OpenAPI 3.1 document.
 */
final readonly class OpenApiGenerator
{
    /**
     * Binds document generation to endpoint metadata, server configuration and framework identity.
     */
    public function __construct(
        private ApiEndpointRegistry $endpoints,
        private ApiConfig $apiConfig,
        private AppConfig $appConfig,
        private FrameworkInfo $frameworkInfo,
    ) {
    }

    /**
     * Generates a fresh document for the endpoints currently held by the registry.
     *
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $paths = [];

        foreach ($this->endpoints->all() as $endpoint) {
            $paths[$this->endpointPath($endpoint->path())][strtolower($endpoint->method())] = $this->operation($endpoint);
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => sprintf('%s API', $this->frameworkInfo->name()),
                'version' => $this->frameworkInfo->version(),
            ],
            'servers' => $this->servers(),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
            ],
        ];
    }

    /**
     * Projects one endpoint into its OpenAPI operation object.
     *
     * @return array<string, mixed>
     */
    private function operation(ApiEndpoint $endpoint): array
    {
        $metadata = $endpoint->metadata();

        $operation = [
            'operationId' => $endpoint->name(),
            'summary' => $endpoint->summary(),
            'description' => $endpoint->description(),
            'tags' => $metadata->tags(),
            'responses' => $this->responses($endpoint),
        ];

        if ($endpoint->access() !== ApiAccess::Public) {
            $operation['security'] = [
                ['BearerAuth' => $metadata->scopes()],
            ];
        }

        if ($endpoint->access() === ApiAccess::InternalOnly) {
            $operation['x-lemonade-access'] = ApiAccess::InternalOnly->value;
        }

        if ($metadata->parameters() !== []) {
            $operation['parameters'] = $metadata->parameters();
        }

        if ($metadata->requestBodySchema() !== null) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => $metadata->requestBodySchema(),
                    ],
                ],
            ];
        }

        return $operation;
    }

    /**
     * Documents configured success responses and standard API failure responses.
     *
     * @return array<int|string, mixed>
     */
    private function responses(ApiEndpoint $endpoint): array
    {
        $metadata = $endpoint->metadata();

        /**
         * @var array<string, mixed> $responses
         */
        $responses = [];

        foreach ($metadata->successStatusCodes() as $statusCode) {
            $responses[(string) $statusCode] = [
                'description' => 'Successful response',
                'content' => [
                    $metadata->responseContentType() => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => $metadata->responseSchema() ?? ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if ($endpoint->access() !== ApiAccess::Public) {
            $responses[(string) HttpStatus::UNAUTHORIZED->value] = [
                'description' => 'Unauthenticated',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ];

            $responses[(string) HttpStatus::FORBIDDEN->value] = [
                'description' => 'Forbidden',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ];
        }

        $responses[(string) HttpStatus::NOT_FOUND->value] = [
            'description' => 'Not Found',
            'content' => [
                'application/problem+json' => [
                    'schema' => [
                        'type' => 'object',
                    ],
                ],
            ],
        ];

        return $responses;
    }

    /**
     * Builds the server URL from the optional application base URL and API prefix.
     *
     * @return list<array<string, string>>
     */
    private function servers(): array
    {
        $prefix = $this->apiPrefix();
        $baseUrl = rtrim($this->appConfig->baseUrl() ?? '', '/');

        if ($baseUrl !== '') {
            return [[
                'url' => $baseUrl . $prefix,
            ]];
        }

        return [[
            'url' => $prefix === '' ? '/' : $prefix,
        ]];
    }

    private function apiPrefix(): string
    {
        return $this->apiConfig->prefix();
    }

    /**
     * Produces the normalized API-relative path used as an OpenAPI path key.
     *
     * @return non-empty-string
     */
    private function endpointPath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        if ($normalized === '/') {
            return '/';
        }

        $trimmed = rtrim($normalized, '/');

        return $trimmed !== '' ? $trimmed : '/';
    }
}
