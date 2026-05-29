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
        private readonly FrameworkInfo $frameworkInfo,
        private readonly Config $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $paths = [];

        foreach ($this->endpoints->all() as $endpoint) {
            $paths[$this->prefixedPath($endpoint->path())][strtolower($endpoint->method())] = $this->operation($endpoint);
        }

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Lemonade Framework API',
                'version' => $this->frameworkInfo->version(),
            ],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
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
        $operation = [
            'operationId' => $endpoint->name(),
            'summary' => $endpoint->summary(),
            'description' => $endpoint->description(),
            'tags' => $endpoint->tags(),
            'responses' => $this->responses($endpoint),
        ];

        if ($endpoint->access() !== ApiAccess::Public) {
            $operation['security'] = [
                ['bearerAuth' => []],
            ];
        }

        return $operation;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function responses(ApiEndpoint $endpoint): array
    {
        /** @var array<string, mixed> $responses */
        $responses = [];

        foreach ($endpoint->successStatusCodes() as $statusCode) {
            $responses[(string) $statusCode] = [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
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

        return $responses;
    }

    private function prefixedPath(string $path): string
    {
        $prefix = $this->config->string('api.prefix', '/api') ?? '/api';
        $normalizedPrefix = '/' . trim($prefix, '/');
        $normalizedPrefix = $normalizedPrefix === '/' ? '' : rtrim($normalizedPrefix, '/');

        return $normalizedPrefix . $path;
    }
}
