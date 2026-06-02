<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Api\Framework;

use Lemonade\Framework\Api\ApiServiceProvider;
use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Api\Framework\FrameworkApiEndpointProvider;
use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\Kernel;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class FrameworkApiEndpointsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-api-' . uniqid('', true);
        $this->writeDefaultConfigFiles();
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testGetFrameworkHealthReturnsJson200(): void
    {
        $response = $this->kernel()->run(new ServerRequest('GET', '/api/framework/health'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $data = $decoded['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame('ok', $data['status'] ?? null);
        self::assertSame('Lemonade Framework', $data['service'] ?? null);
        self::assertSame('1.0.0', $data['version'] ?? null);
        $meta = $decoded['meta'] ?? null;
        self::assertIsArray($meta);
        self::assertIsString($meta['timestamp'] ?? null);
    }

    public function testHeadFrameworkHealthReturns200WithoutBody(): void
    {
        $kernel = $this->kernel();
        ob_start();
        $kernel->handle(new ServerRequest('HEAD', '/api/framework/health'));
        $output = ob_get_clean();

        self::assertSame('', is_string($output) ? $output : '');
        self::assertSame(200, http_response_code());
    }

    public function testGetOpenApiWithoutTokenReturns401ProblemJson(): void
    {
        $response = $this->kernel()->run(new ServerRequest('GET', '/api/framework/openapi.json'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testGetOpenApiWithAdminTokenReturns200Json(): void
    {
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'security' => [\n        'static_bearer' => [\n            'enabled' => true,\n            'token' => 'secret-token',\n            'scopes' => ['api:admin'],\n        ],\n    ],\n];\n",
        );

        $request = (new ServerRequest('GET', '/api/framework/openapi.json'))
            ->withHeader('Authorization', 'Bearer secret-token');

        $response = $this->kernel()->run($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testHealthIsAvailableWhenFrameworkEndpointsAreDisabled(): void
    {
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'framework' => [\n        'enabled' => false,\n    ],\n];\n",
        );

        $health = $this->kernel()->run(new ServerRequest('GET', '/api/framework/health'));
        $openapi = $this->kernel()->run(new ServerRequest('GET', '/api/framework/openapi.json'));
        $docs = $this->kernel()->run(new ServerRequest('GET', '/api/framework/docs'));

        self::assertSame(200, $health->getStatusCode());
        self::assertSame(404, $openapi->getStatusCode());
        self::assertSame(404, $docs->getStatusCode());
    }

    public function testDocsEndpointIsDisabledByDefault(): void
    {
        $response = $this->kernel()->run(new ServerRequest('GET', '/api/framework/docs'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDocsEndpointCanBeEnabled(): void
    {
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'framework' => [\n        'docs' => [\n            'enabled' => true,\n        ],\n    ],\n];\n",
        );

        $response = $this->kernel()->run(new ServerRequest('GET', '/api/framework/docs'));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testApiEnabledFalseDisablesWholeApi(): void
    {
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'enabled' => false,\n];\n",
        );

        $health = $this->kernel()->run(new ServerRequest('GET', '/api/framework/health'));
        $openapi = $this->kernel()->run(new ServerRequest('GET', '/api/framework/openapi.json'));

        self::assertSame(404, $health->getStatusCode());
        self::assertSame(404, $openapi->getStatusCode());
    }

    public function testOpenApiContainsAppEndpointFromConfiguredProvider(): void
    {
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'endpoint_providers' => [\n        '" . addslashes(TestAppApiEndpointProvider::class) . "',\n    ],\n    'security' => [\n        'static_bearer' => [\n            'enabled' => true,\n            'token' => 'secret-token',\n            'scopes' => ['api:admin'],\n        ],\n    ],\n];\n",
        );

        $request = (new ServerRequest('GET', '/api/framework/openapi.json'))
            ->withHeader('Authorization', 'Bearer secret-token');
        $response = $this->kernel()->run($request);

        self::assertSame(200, $response->getStatusCode());
        $decoded = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $paths = $decoded['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertArrayHasKey('/app/ping', $paths);
    }

    public function testConfiguredApiEndpointProviderMustImplementInterface(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);
        $framework->config([
            'api' => [
                'endpoint_providers' => [
                    TestInvalidApiEndpointProvider::class,
                ],
            ],
        ]);

        $this->expectException(\LogicException::class);
        $framework->register(new ApiServiceProvider());
    }

    public function testApiServiceProviderExplicitlyBindsFrameworkApiEndpointProvider(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);

        $framework->register(new ApiServiceProvider());

        self::assertTrue($container->isBound(FrameworkApiEndpointProvider::class));
    }

    public function testFrameworkRunsWithDefaultsWhenAppApiConfigFileIsMissing(): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Api.php';
        @unlink($path);

        $response = $this->kernel()->run(new ServerRequest('GET', '/api/framework/health'));

        self::assertSame(200, $response->getStatusCode());
    }

    public function testAppApiConfigWithOnlyStaticBearerTokenKeepsOtherDefaults(): void
    {
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'security' => [\n        'static_bearer' => [\n            'token' => 'secret-token',\n        ],\n    ],\n];\n",
        );

        $kernel = $this->kernel();
        $kernel->bootstrap();
        $config = $kernel->framework()->container()->get(Config::class);

        self::assertTrue($config->bool('api.enabled'));
        self::assertSame('/api', $config->string('api.prefix'));
        self::assertTrue($config->bool('api.framework.openapi.enabled'));
        self::assertFalse($config->bool('api.security.static_bearer.enabled'));
        self::assertSame('secret-token', $config->string('api.security.static_bearer.token'));
    }

    public function testStaticBearerEnabledWithNullTokenDoesNotCrashAndAuthFails(): void
    {
        $this->writeConfigFile(
            'Api.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'security' => [\n        'static_bearer' => [\n            'enabled' => true,\n            'token' => null,\n        ],\n    ],\n];\n",
        );

        $response = $this->kernel()->run(new ServerRequest('GET', '/api/framework/openapi.json'));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testApiPrefixNormalizationAcceptsApiVariants(): void
    {
        foreach (['/api', 'api', '/api/'] as $prefix) {
            $this->writeConfigFile(
                'Api.php',
                "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'prefix' => '" . addslashes($prefix) . "',\n];\n",
            );

            $response = $this->kernel()->run(new ServerRequest('GET', '/api/framework/health'));
            self::assertSame(200, $response->getStatusCode(), 'Failed for prefix: ' . $prefix);
        }
    }

    private function kernel(): Kernel
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);

        return new Kernel($context, $container, $framework, new ResponseEmitter());
    }

    private function writeDefaultConfigFiles(): void
    {
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        foreach ([
            'Config.php',
            'App.php',
            'Localization.php',
            'Cache.php',
            'Logging.php',
            'Session.php',
            'Database.php',
            'Breadcrumbs.php',
            'Upload.php',
            'Api.php',
            'Providers.php',
        ] as $file) {
            if ($file === 'Config.php') {
                $this->writeConfigFile(
                    'Config.php',
                    "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php' => null, 'Localization.php' => null, 'Cache.php' => null, 'Logging.php' => null, 'Session.php' => null, 'Database.php' => null, 'Breadcrumbs.php' => null, 'Upload.php' => null, 'Api.php' => 'api', 'Providers.php' => null], 'http' => [], 'cli' => ['Commands.php' => 'commands']];\n",
                );
                continue;
            }

            $this->writeConfigFile($file, "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
        }

        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n};\n",
        );
    }

    private function writeConfigFile(string $file, string $contents): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . $file;
        file_put_contents($path, $contents);
    }

    private function deleteRecursive(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->deleteRecursive($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}

final class TestAppApiEndpointProvider implements ApiEndpointProviderInterface
{
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get(
            path: '/app/ping',
            handler: 'AppPingController@show',
            name: 'app.ping',
            summary: 'App ping',
            description: 'App ping endpoint',
            access: ApiAccess::Public,
            metadata: new ApiEndpointMetadata(
                tags: ['App'],
            ),
        );
    }
}

final class TestInvalidApiEndpointProvider {}
