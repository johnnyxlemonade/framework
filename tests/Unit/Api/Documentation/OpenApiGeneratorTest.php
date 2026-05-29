<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Documentation;

use Lemonade\Framework\Api\Documentation\OpenApiGenerator;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\FrameworkInfo;
use PHPUnit\Framework\TestCase;

final class OpenApiGeneratorTest extends TestCase
{
    public function testPublicEndpointHasNoSecurityAndProtectedHasBearerAuth(): void
    {
        $registry = new ApiEndpointRegistry();
        $registry->get(
            path: '/framework/health',
            handler: 'HealthController@show',
            name: 'framework.health',
            summary: 'Health',
            description: 'Health endpoint',
            access: ApiAccess::Public,
            metadata: new ApiEndpointMetadata(
                tags: ['Framework'],
            ),
        );
        $registry->get(
            path: '/framework/openapi.json',
            handler: 'OpenApiController@show',
            name: 'framework.openapi',
            summary: 'OpenAPI',
            description: 'OpenAPI endpoint',
            access: ApiAccess::Protected,
            metadata: new ApiEndpointMetadata(
                tags: ['Framework'],
                scopes: ['openapi:read'],
                parameters: [[
                    'name' => 'id',
                    'in' => 'query',
                    'required' => false,
                    'schema' => ['type' => 'string'],
                ]],
                requestBodySchema: ['type' => 'object'],
                responseSchema: ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']]],
                successStatusCodes: [201],
            ),
        );
        $registry->get(
            path: '/framework/docs',
            handler: 'DocsController@show',
            name: 'framework.docs',
            summary: 'Docs',
            description: 'Docs endpoint',
            access: ApiAccess::Protected,
            metadata: new ApiEndpointMetadata(
                tags: ['Framework'],
                scopes: ['openapi:read'],
                responseContentType: 'text/html; charset=utf-8',
            ),
        );

        $config = new Config([
            'api' => ['prefix' => '/api'],
            'app' => ['base_url' => 'https://example.test'],
        ]);

        $document = (new OpenApiGenerator($registry, $config, new FrameworkInfo()))->generate();

        self::assertSame('3.1.0', $document['openapi']);
        $info = $document['info'] ?? null;
        self::assertIsArray($info);
        self::assertSame('Lemonade Framework API', $info['title'] ?? null);
        self::assertSame('1.0.0', $info['version'] ?? null);
        $servers = $document['servers'] ?? null;
        self::assertIsArray($servers);
        $firstServer = $servers[0] ?? null;
        self::assertIsArray($firstServer);
        self::assertSame('https://example.test/api', $firstServer['url'] ?? null);

        $paths = $document['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/framework/health', $paths);
        self::assertArrayHasKey('/framework/openapi.json', $paths);
        self::assertArrayHasKey('/framework/docs', $paths);
        self::assertArrayNotHasKey('/api/framework/health', $paths);

        $healthPath = $paths['/framework/health'] ?? null;
        self::assertIsArray($healthPath);
        $healthGet = $healthPath['get'] ?? null;
        self::assertIsArray($healthGet);
        self::assertArrayNotHasKey('security', $healthGet);

        $openapiPath = $paths['/framework/openapi.json'] ?? null;
        self::assertIsArray($openapiPath);
        $openapiGet = $openapiPath['get'] ?? null;
        self::assertIsArray($openapiGet);
        self::assertSame([['BearerAuth' => ['openapi:read']]], $openapiGet['security'] ?? null);
        $parameters = $openapiGet['parameters'] ?? null;
        self::assertIsArray($parameters);
        $firstParameter = $parameters[0] ?? null;
        self::assertIsArray($firstParameter);
        self::assertSame('id', $firstParameter['name'] ?? null);
        $responses = $openapiGet['responses'] ?? null;
        self::assertIsArray($responses);
        self::assertArrayHasKey('201', $responses);
        self::assertArrayHasKey('401', $responses);
        self::assertArrayHasKey('403', $responses);
        self::assertArrayHasKey('404', $responses);

        $docsPath = $paths['/framework/docs'] ?? null;
        self::assertIsArray($docsPath);
        $docsGet = $docsPath['get'] ?? null;
        self::assertIsArray($docsGet);
        self::assertSame([['BearerAuth' => ['openapi:read']]], $docsGet['security'] ?? null);
        $docsResponses = $docsGet['responses'] ?? null;
        self::assertIsArray($docsResponses);
        $docs200 = $docsResponses['200'] ?? null;
        self::assertIsArray($docs200);
        $docs200Content = $docs200['content'] ?? null;
        self::assertIsArray($docs200Content);
        self::assertArrayHasKey('text/html; charset=utf-8', $docs200Content);
        $docsHtmlContent = $docs200Content['text/html; charset=utf-8'] ?? null;
        self::assertIsArray($docsHtmlContent);
        $docsHtmlSchema = $docsHtmlContent['schema'] ?? null;
        self::assertIsArray($docsHtmlSchema);
        self::assertSame('object', $docsHtmlSchema['type'] ?? null);
    }
}
