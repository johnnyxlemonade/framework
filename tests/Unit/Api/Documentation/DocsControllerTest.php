<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Documentation;

use Lemonade\Framework\Api\Config\ApiConfig;
use Lemonade\Framework\Api\Config\ApiEndpointConfig;
use Lemonade\Framework\Api\Config\ApiSecurityConfig;
use Lemonade\Framework\Api\Config\FrameworkApiConfig;
use Lemonade\Framework\Api\Documentation\DocsController;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class DocsControllerTest extends TestCase
{
    public function testShowRendersSwaggerUiWithConfiguredOpenApiUrl(): void
    {
        $controller = new DocsController(
            new ApiConfig(
                enabled: true,
                prefix: '/internal-api',
                endpointProviders: [],
                security: new ApiSecurityConfig(null),
                framework: new FrameworkApiConfig(
                    enabled: true,
                    health: new ApiEndpointConfig(true, '/framework/health', ApiAccess::Public, []),
                    openapi: new ApiEndpointConfig(true, '/spec/openapi.json', ApiAccess::Public, []),
                    docs: new ApiEndpointConfig(true, '/framework/docs', ApiAccess::Public, []),
                ),
            ),
            new Psr17Factory(),
        );

        $response = $controller->show();
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringNotContainsString('/assets/framework/vendor/swagger-ui/', $body);
        self::assertStringContainsString('https://static.lemonadeframework.cz/swagger/5.32.14/swagger-ui.css', $body);
        self::assertStringContainsString('https://static.lemonadeframework.cz/swagger/5.32.14/swagger-docs-lemonade.css', $body);
        self::assertStringContainsString('https://static.lemonadeframework.cz/swagger/5.32.14/swagger-ui-bundle.js', $body);
        self::assertStringContainsString('https://static.lemonadeframework.cz/swagger/5.32.14/swagger-ui-standalone-preset.js', $body);
        self::assertStringNotContainsString('swagger-themes@', $body);
        self::assertStringContainsString('window.localStorage.getItem(storageKey)', $body);
        self::assertStringContainsString('window.localStorage.setItem(storageKey,nextTheme);', $body);
        self::assertStringContainsString('data-theme-toggle', $body);
        self::assertStringContainsString('const theme=storedTheme==="light"||storedTheme==="dark"?storedTheme:"dark";', $body);
        self::assertStringContainsString('SwaggerUIBundle({url:"\/internal-api\/spec\/openapi.json"', $body);
        self::assertStringContainsString('url:"\/internal-api\/spec\/openapi.json"', $body);
        self::assertStringContainsString('<a href="/internal-api/spec/openapi.json">OpenAPI JSON</a>', $body);
        self::assertStringContainsString('dom_id:"#swagger-ui"', $body);
    }
}
