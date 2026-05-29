<?php

declare(strict_types=1);

namespace Lemonade\Framework\Api\Documentation;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpoint;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\FrameworkInfo;

final class OpenApiGenerator
{
    public function __construct(
        private readonly ApiEndpointRegistry $endpoints,
        private readonly Config $config,
        private readonly FrameworkInfo $frameworkInfo,
    ) {}

    /**
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
     * @return array<int|string, mixed>
     */
    private function responses(ApiEndpoint $endpoint): array
    {
        $metadata = $endpoint->metadata();

        /** @var array<string, mixed> $responses */
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
            $responses['401'] = [
                'description' => 'Unauthenticated',
                'content' => [
                    'application/problem+json' => [
                        'schema' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ];

            $responses['403'] = [
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

        $responses['404'] = [
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
     * @return list<array<string, string>>
     */
    private function servers(): array
    {
        $prefix = $this->apiPrefix();
        $baseUrl = rtrim($this->config->string('app.base_url', '') ?? '', '/');

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
        $prefix = $this->config->string('api.prefix', '/api') ?? '/api';
        $normalizedPrefix = '/' . trim($prefix, '/');
        return $normalizedPrefix === '/' ? '' : rtrim($normalizedPrefix, '/');
    }

    /**
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
