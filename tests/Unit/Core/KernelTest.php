<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\Exception\ScopedContainerClosedException;
use Lemonade\Framework\Container\ScopedContainerInterface;
use Lemonade\Framework\Api\Config\ApiConfigDefinition;
use Lemonade\Framework\Core\Config\ConfigLoader;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\Health\FrameworkHealthFastPath;
use Lemonade\Framework\Core\Kernel;
use Lemonade\Framework\Core\KernelFactory;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Http\Psr\ServerRequestFactory;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Lemonade\Framework\Routing\RouteRegistrarInterface;
use Lemonade\Framework\Routing\RouteRegistrarRegistry;
use Lemonade\Framework\Routing\Router;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;

final class KernelTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-kernel-' . uniqid('', true);
        $this->writeDefaultConfigFiles();
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testRunReturns404ForRouteNotFound(): void
    {
        $this->writeThrowingConfig(RouteNotFoundException::class, 'Not found in bootstrap');
        $kernel = $this->kernel(false);
        $response = $kernel->run(new ServerRequest('GET', '/anything'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRun404InDebugContainsExceptionMessage(): void
    {
        $this->writeThrowingConfig(RouteNotFoundException::class, 'Not found in bootstrap');
        $kernel = $this->kernel(true);
        $response = $kernel->run(new ServerRequest('GET', '/anything'));
        $body = (string) $response->getBody();

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('404 Not Found', $body);
        self::assertStringContainsString('Not found in bootstrap', $body);
    }

    public function testRun404OutsideDebugContainsOnlyGenericText(): void
    {
        $this->writeThrowingConfig(RouteNotFoundException::class, 'Not found in bootstrap');
        $kernel = $this->kernel(false);
        $response = $kernel->run(new ServerRequest('GET', '/anything'));
        $body = (string) $response->getBody();

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('404 Not Found', $body);
    }

    public function testRunReturns500ForGeneralThrowable(): void
    {
        $this->writeThrowingConfig(\RuntimeException::class, 'Boom from bootstrap');
        $kernel = $this->kernel(false);
        $response = $kernel->run(new ServerRequest('GET', '/anything'));

        self::assertSame(500, $response->getStatusCode());
    }

    public function testRunReturnsPreBootstrapErrorUsingEarlyPsr17Factory(): void
    {
        @unlink($this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Config.yaml');
        $kernel = $this->kernel(false);
        $factory = $kernel->container()->get(Psr17Factory::class);

        $response = $kernel->run(new ServerRequest('GET', '/anything'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame($factory, $kernel->container()->get(Psr17Factory::class));
    }

    public function testRun500InDebugContainsClassAndMessage(): void
    {
        $this->writeThrowingConfig(\RuntimeException::class, 'Boom from bootstrap');
        $kernel = $this->kernel(true);
        $response = $kernel->run(new ServerRequest('GET', '/anything'));
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString(\RuntimeException::class, $body);
        self::assertStringContainsString('Boom from bootstrap', $body);
    }

    public function testRun500OutsideDebugContainsOnlyGenericText(): void
    {
        $this->writeThrowingConfig(\RuntimeException::class, 'Boom from bootstrap');
        $kernel = $this->kernel(false);
        $response = $kernel->run(new ServerRequest('GET', '/anything'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('500 Internal Server Error', (string) $response->getBody());
    }

    public function testRunExceptionIsLoggedByExceptionLogger(): void
    {
        $routingPath = $this->root
            . DIRECTORY_SEPARATOR . 'app'
            . DIRECTORY_SEPARATOR . 'Config'
            . DIRECTORY_SEPARATOR . 'Routing.php';
        @unlink($routingPath);

        $kernel = $this->kernel(false);
        $kernel->run(new ServerRequest('GET', '/anything'));

        $logPath = $this->root
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'writable'
            . DIRECTORY_SEPARATOR . 'logs'
            . DIRECTORY_SEPARATOR . 'error-' . date('Y-m-d') . '.log';

        self::assertFileExists($logPath);
        $contents = file_get_contents($logPath);
        self::assertIsString($contents);
        self::assertStringContainsString('Routing file not found', $contents);
    }

    public function testHandlePassesResponseToEmitter(): void
    {
        $this->writeThrowingConfig(RouteNotFoundException::class, 'Not found in bootstrap');
        $kernel = $this->kernel(false);

        ob_start();
        $kernel->handle(new ServerRequest('GET', '/anything'));
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('404 Not Found', $output);
    }

    public function testBootstrapIsIdempotent(): void
    {
        $kernel = $this->kernel(false);

        $kernel->bootstrap();
        $kernel->bootstrap();

        $response = $kernel->run(new ServerRequest('GET', '/missing'));
        self::assertSame(404, $response->getStatusCode());
    }

    public function testBootstrapRegistersHttpMiddlewareServices(): void
    {
        $kernel = $this->kernel(false);
        $kernel->bootstrap();

        $container = $kernel->container();

        self::assertTrue($container->isBound(MiddlewareStack::class));
        self::assertTrue($container->isBound(MiddlewareResolver::class));
    }

    public function testRunDoesNotBindRequestIntoRootContainerBeforeApplicationProvidersRegister(): void
    {
        $this->writeConfigFile(
            'Config.yaml',
            "shared:\n  - App\n  - Api\n  - Providers\nhttp: []\ncli:\n  - Commands\n",
        );
        $this->writeConfigFile(
            'Providers.yaml',
            "module: providers\nconfig:\n  providers:\n    - Lemonade\\Framework\\Tests\\Unit\\Core\\KernelRequestProbeProvider\n",
        );
        KernelRequestProbeProvider::$request = null;
        $request = new ServerRequest('GET', '/provider-request');
        $kernel = $this->kernel(false);

        $kernel->run($request);

        self::assertNull(KernelRequestProbeProvider::$request);
        self::assertFalse($kernel->container()->isBound(ServerRequestInterface::class));
    }

    public function testExplicitBootstrapRemainsRequestlessForApplicationProviders(): void
    {
        $this->writeConfigFile(
            'Config.yaml',
            "shared:\n  - App\n  - Api\n  - Providers\nhttp: []\ncli:\n  - Commands\n",
        );
        $this->writeConfigFile(
            'Providers.yaml',
            "module: providers\nconfig:\n  providers:\n    - Lemonade\\Framework\\Tests\\Unit\\Core\\KernelRequestProbeProvider\n",
        );
        KernelRequestProbeProvider::$request = null;
        $kernel = $this->kernel(false);

        $kernel->bootstrap();

        self::assertNull(KernelRequestProbeProvider::$request);
        self::assertFalse($kernel->container()->isBound(ServerRequestInterface::class));
    }

    public function testBootstrapRunsBootableProviderAfterAllConfiguredProvidersRegister(): void
    {
        $this->writeConfigFile(
            'Config.yaml',
            "shared:\n  - App\n  - Api\n  - Providers\nhttp: []\ncli:\n  - Commands\n",
        );
        $this->writeConfigFile(
            'Providers.yaml',
            "module: providers\nconfig:\n  providers:\n    - Lemonade\\Framework\\Tests\\Unit\\Core\\KernelBootDependencyProvider\n    - Lemonade\\Framework\\Tests\\Unit\\Core\\KernelBootObserverProvider\n",
        );
        KernelBootObserverProvider::$resolved = false;

        $kernel = $this->kernel(false);
        $kernel->bootstrap();

        self::assertTrue(KernelBootObserverProvider::$resolved);
    }

    public function testBootstrapExecutesProviderRouteRegistrarsAfterApplicationRoutesAndFreezesRouting(): void
    {
        $this->writeConfigFile(
            'Config.yaml',
            "shared:\n  - App\n  - Api\n  - Providers\nhttp: []\ncli:\n  - Commands\n",
        );
        $this->writeConfigFile(
            'Providers.yaml',
            "module: providers\nconfig:\n  providers:\n    - Lemonade\\Framework\\Tests\\Unit\\Core\\KernelRouteRegistrarProvider\n",
        );
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n    \$router->get('/application-phase', 'KernelRouteRegistrarSupportController@application');\n};\n",
        );

        $kernel = $this->kernel(false);
        $kernel->bootstrap();

        $container = $kernel->container();
        $router = $container->get(Router::class);
        $registry = $container->get(RouteRegistrarRegistry::class);

        self::assertTrue($router->isFrozen());
        self::assertTrue($registry->isFrozen());
        self::assertSame(
            'App\\Controllers\\KernelRouteRegistrarSupportController',
            $router->match(new ServerRequest('GET', '/application-phase'))->controller(),
        );
        self::assertSame(
            'App\\Controllers\\KernelRouteRegistrarSupportController',
            $router->match(new ServerRequest('GET', '/provider-phase'))->controller(),
        );
    }

    public function testBootstrapSkipsMissingConventionalConfigFiles(): void
    {
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Api.yaml');

        $kernel = $this->kernel(false);
        $response = $kernel->run(new ServerRequest('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testKernelConventionalConfigDoesNotIncludeCommandsYaml(): void
    {
        $this->writeConfigFile(
            'Commands.yaml',
            "module: commands\nconfig:\n  commands:\n    - invalid\n",
        );

        $kernel = $this->kernel(false);
        $response = $kernel->run(new ServerRequest('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testRunHeadMissingRouteReturnsSameStatusAsGet(): void
    {
        $kernel = $this->kernel(false);

        $getResponse = $kernel->run(new ServerRequest('GET', '/missing'));
        $headResponse = $kernel->run(new ServerRequest('HEAD', '/missing'));

        self::assertSame($getResponse->getStatusCode(), $headResponse->getStatusCode());
        self::assertSame(404, $headResponse->getStatusCode());
    }

    public function testProductionWarmCacheMissingRouteStillReturns404(): void
    {
        $this->warmHttpConfigCache();
        $kernel = $this->kernel(false, Environment::Production);

        $response = $kernel->run(new ServerRequest('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testProductionWarmCacheNonHealthFailureStillReturns500(): void
    {
        $this->writeThrowingConfig(\RuntimeException::class, 'Boom from bootstrap');
        $this->warmHttpConfigCache();
        $kernel = $this->kernel(false, Environment::Production);

        $response = $kernel->run(new ServerRequest('GET', '/anything'));

        self::assertSame(500, $response->getStatusCode());
    }

    public function testHandleCreatesRequestFromGlobalsWhenNullProvided(): void
    {
        $this->writeRoutingHeadFallbackTarget();
        $kernel = $this->kernel(true);
        $factory = $kernel->container()->get(Psr17Factory::class);

        $originalServer = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['REQUEST_URI'] = '/head-fallback';

        try {
            ob_start();
            $kernel->handle(null);
            $output = ob_get_clean();
        } finally {
            $_SERVER = $originalServer;
        }

        self::assertSame('', is_string($output) ? $output : '');
        self::assertSame(207, http_response_code());

        $serverRequestFactory = $kernel->container()->get(ServerRequestFactory::class);
        $property = new ReflectionProperty(ServerRequestFactory::class, 'psr17Factory');

        self::assertSame($factory, $property->getValue($serverRequestFactory));
    }

    public function testHandleOptionsOnPathWithGetRouteReturns204WithoutBody(): void
    {
        $this->writeRoutingOptionsAutoTarget();
        $kernel = $this->kernel(false);

        ob_start();
        $kernel->handle(new ServerRequest('OPTIONS', '/options-auto'));
        $output = ob_get_clean();

        self::assertSame('', is_string($output) ? $output : '');
        self::assertSame(204, http_response_code());

        $headers = function_exists('headers_list') ? headers_list() : [];
        if ($headers !== []) {
            self::assertContains('Allow: GET, HEAD, OPTIONS', $headers);
        } else {
            self::addToAssertionCount(1);
        }
    }

    public function testHandleOptionsOnMissingPathFallsBackTo404(): void
    {
        $kernel = $this->kernel(false);

        ob_start();
        $kernel->handle(new ServerRequest('OPTIONS', '/missing'));
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertNotSame('', $output);
        self::assertSame(404, http_response_code());
    }

    public function testHandleOptionsUsesExplicitOptionsRouteWhenRegistered(): void
    {
        $this->writeRoutingExplicitOptionsTarget();
        $kernel = $this->kernel(false);

        ob_start();
        $kernel->handle(new ServerRequest('OPTIONS', '/options-explicit'));
        $output = ob_get_clean();

        self::assertSame('explicit-options', is_string($output) ? $output : '');
        self::assertSame(209, http_response_code());
    }

    public function testKernelFactoryWiresHealthFastPathForDefaultHealthRequest(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );

        $kernel = (new KernelFactory())->create($context);
        $response = $kernel->run(new ServerRequest('GET', '/api/framework/health'));

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($kernel->container()->isBound(MiddlewareStack::class));

        $definitions = $kernel->container()->get(ConfigDefinitionRegistry::class);
        self::assertCount(2, $definitions->entriesFor(ApiConfigDefinition::moduleKey()));

        $kernel->bootstrap();

        self::assertCount(2, $definitions->entriesFor(ApiConfigDefinition::moduleKey()));
    }

    public function testHealthFastPathClosesTheRequestScope(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $container = new KernelScopeTrackingContainer(new Container());
        $framework = new Framework($container, $context);
        $kernel = new Kernel(
            $context,
            $container,
            $framework,
            new ResponseEmitter(),
            new FrameworkHealthFastPath(
                $container->get(ConfigDefinitionRegistry::class),
                $container->get(Benchmark::class),
            ),
            $container->get(Benchmark::class),
        );

        $response = $kernel->run(new ServerRequest('GET', '/api/framework/health'));

        self::assertSame(200, $response->getStatusCode());
        $scope = $container->lastScope;
        self::assertInstanceOf(ScopedContainerInterface::class, $scope);

        $this->expectException(ScopedContainerClosedException::class);
        $scope->get('anything');
    }

    public function testEachRequestUsesAnIsolatedScopeWithoutLeakingRequestIntoRoot(): void
    {
        $this->writeRequestScopeRouting();
        \App\Controllers\RequestScopeKernelController::reset();
        $kernel = $this->kernel(false);
        $root = $kernel->container();
        $root->scoped(RequestScopeKernelService::class, RequestScopeKernelService::class);
        $root->singleton(RequestScopeKernelSingleton::class, RequestScopeKernelSingleton::class);
        $firstRequest = new ServerRequest('GET', '/request-scope?request=first');
        $secondRequest = new ServerRequest('GET', '/request-scope?request=second');

        $firstResponse = $kernel->run($firstRequest);
        $secondResponse = $kernel->run($secondRequest);

        self::assertSame(200, $firstResponse->getStatusCode());
        self::assertSame(200, $secondResponse->getStatusCode());
        self::assertSame([$firstRequest, $secondRequest], \App\Controllers\RequestScopeKernelController::$requests);
        self::assertCount(2, \App\Controllers\RequestScopeKernelController::$containers);
        self::assertInstanceOf(ScopedContainerInterface::class, \App\Controllers\RequestScopeKernelController::$containers[0]);
        self::assertNotSame(\App\Controllers\RequestScopeKernelController::$containers[0], \App\Controllers\RequestScopeKernelController::$containers[1]);
        self::assertNotSame(\App\Controllers\RequestScopeKernelController::$services[0], \App\Controllers\RequestScopeKernelController::$services[1]);
        self::assertSame(\App\Controllers\RequestScopeKernelController::$singletons[0], \App\Controllers\RequestScopeKernelController::$singletons[1]);
        self::assertFalse($root->isBound(ServerRequestInterface::class));

        $this->expectException(ScopedContainerClosedException::class);
        \App\Controllers\RequestScopeKernelController::$containers[0]->get(RequestScopeKernelService::class);
    }

    public function testRequestScopeClosesAfterRouteNotFound(): void
    {
        KernelScopeCaptureMiddleware::reset();
        $kernel = $this->kernel(false);
        $kernel->container()->scoped(KernelScopeCaptureMiddleware::class, KernelScopeCaptureMiddleware::class);
        $kernel->framework()->middleware(static function (MiddlewareStack $stack): void {
            $stack->prepend(KernelScopeCaptureMiddleware::class);
        });

        $response = $kernel->run(new ServerRequest('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
        $scope = KernelScopeCaptureMiddleware::$container;
        self::assertInstanceOf(ScopedContainerInterface::class, $scope);

        $this->expectException(ScopedContainerClosedException::class);
        $scope->get('anything');
    }

    public function testRequestScopeClosesAfterControllerExceptionHandledByErrorMiddleware(): void
    {
        $this->writeRequestScopeThrowingRouting();
        \App\Controllers\RequestScopeThrowingController::$container = null;
        $kernel = $this->kernel(false);

        $response = $kernel->run(new ServerRequest('GET', '/request-scope-throw'));

        self::assertSame(500, $response->getStatusCode());
        $scope = \App\Controllers\RequestScopeThrowingController::$container;
        self::assertInstanceOf(ScopedContainerInterface::class, $scope);

        $this->expectException(ScopedContainerClosedException::class);
        $scope->get('anything');
    }

    public function testRequestScopeClosesAfterMiddlewareException(): void
    {
        KernelThrowingScopeMiddleware::$container = null;
        $kernel = $this->kernel(false);
        $kernel->container()->scoped(KernelThrowingScopeMiddleware::class, KernelThrowingScopeMiddleware::class);
        $kernel->framework()->middleware(static function (MiddlewareStack $stack): void {
            $stack->insertAfter(ErrorHandlingMiddleware::class, KernelThrowingScopeMiddleware::class);
        });

        $response = $kernel->run(new ServerRequest('GET', '/missing'));

        self::assertSame(500, $response->getStatusCode());
        $scope = KernelThrowingScopeMiddleware::$container;
        self::assertInstanceOf(ScopedContainerInterface::class, $scope);

        $this->expectException(ScopedContainerClosedException::class);
        $scope->get('anything');
    }

    private function kernel(bool $debug, Environment $environment = Environment::Testing): Kernel
    {
        $context = new ApplicationContext(
            $environment,
            new Path($this->root),
            $debug ? DebugMode::enabled() : DebugMode::disabled(),
        );
        $container = new Container();
        $framework = new Framework($container, $context);

        return new Kernel(
            $context,
            $container,
            $framework,
            new ResponseEmitter(),
            new FrameworkHealthFastPath(
                $container->get(ConfigDefinitionRegistry::class),
                $container->get(Benchmark::class),
            ),
            $container->get(Benchmark::class),
        );
    }

    private function writeDefaultConfigFiles(): void
    {
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0775, true);
        }

        $defaults = [
            'Config.yaml',
            'App.yaml',
            'Api.yaml',
            'Commands.yaml',
        ];

        foreach ($defaults as $file) {
            if ($file === 'Config.yaml') {
                $this->writeConfigFile(
                    'Config.yaml',
                    "shared:\n  - App\n  - Api\nhttp: []\ncli:\n  - Commands\n",
                );
                continue;
            }

            $this->writeConfigFile($file, match ($file) {
                'App.yaml' => "module: app\nconfig: {}\n",
                'Api.yaml' => "module: api\nconfig: {}\n",
                'Commands.yaml' => "module: commands\nconfig:\n  commands: []\n",
            });
        }

        $this->writeRoutingNoRoutes();
    }

    private function writeRoutingNoRoutes(): void
    {
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n};\n",
        );
    }

    private function writeRoutingHeadFallbackTarget(): void
    {
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n    \$router->get('/head-fallback', 'HeadKernelSupportController@index');\n};\n",
        );
    }

    private function writeRoutingOptionsAutoTarget(): void
    {
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n    \$router->get('/options-auto', 'OptionsKernelSupportController@index');\n};\n",
        );
    }

    private function writeRoutingExplicitOptionsTarget(): void
    {
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n    \$router->get('/options-explicit', 'OptionsKernelSupportController@index');\n    \$router->options('/options-explicit', 'OptionsKernelSupportController@options');\n};\n",
        );
    }

    private function writeRequestScopeRouting(): void
    {
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n    \$router->get('/request-scope', 'RequestScopeKernelController@index');\n};\n",
        );
    }

    private function writeRequestScopeThrowingRouting(): void
    {
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n    \$router->get('/request-scope-throw', 'RequestScopeThrowingController@index');\n};\n",
        );
    }

    private function writeThrowingConfig(string $exceptionClass, string $message): void
    {
        $this->writeConfigFile(
            'Routing.php',
            "<?php\n\ndeclare(strict_types=1);\n\nuse Lemonade\\Framework\\Routing\\Router;\n\nreturn static function (Router \$router): void {\n    unset(\$router);\n    throw new {$exceptionClass}('" . addslashes($message) . "');\n};\n",
        );
    }

    private function writeConfigFile(string $file, string $contents): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . $file;
        file_put_contents($path, $contents);
    }

    private function warmHttpConfigCache(): void
    {
        $context = new ApplicationContext(
            Environment::Production,
            new Path($this->root),
            DebugMode::disabled(),
        );
        $framework = new Framework(new Container(), $context);

        (new ConfigLoader())->loadApplication($framework, $context, ConfigLoader::ENTRYPOINT_HTTP);
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

namespace App\Controllers;

use Lemonade\Framework\Core\AbstractController;
use Psr\Http\Message\ResponseInterface;

final class HeadKernelSupportController extends AbstractController
{
    public function index(): ResponseInterface
    {
        return $this->response('head-kernel-body', 207, 'text/plain; charset=UTF-8')
            ->withHeader('X-Head-Kernel', 'ok');
    }
}

final class OptionsKernelSupportController extends AbstractController
{
    public function index(): ResponseInterface
    {
        return $this->response('options-index', 200, 'text/plain; charset=UTF-8');
    }

    public function options(): ResponseInterface
    {
        return $this->response('explicit-options', 209, 'text/plain; charset=UTF-8');
    }
}

final class KernelRouteRegistrarSupportController extends AbstractController
{
    public function application(): ResponseInterface
    {
        return $this->response('application');
    }

    public function provider(): ResponseInterface
    {
        return $this->response('provider');
    }
}

final class RequestScopeKernelController
{
    /** @var list<\Psr\Http\Message\ServerRequestInterface> */
    public static array $requests = [];

    /** @var list<\Lemonade\Framework\Container\ContainerInterface> */
    public static array $containers = [];

    /** @var list<\Lemonade\Framework\Tests\Unit\Core\RequestScopeKernelService> */
    public static array $services = [];

    /** @var list<\Lemonade\Framework\Tests\Unit\Core\RequestScopeKernelSingleton> */
    public static array $singletons = [];

    public function __construct(
        private readonly \Psr\Http\Message\ServerRequestInterface $request,
        private readonly \Lemonade\Framework\Container\ContainerInterface $container,
        private readonly \Lemonade\Framework\Tests\Unit\Core\RequestScopeKernelService $service,
        private readonly \Lemonade\Framework\Tests\Unit\Core\RequestScopeKernelSingleton $singleton,
    ) {}

    public static function reset(): void
    {
        self::$requests = [];
        self::$containers = [];
        self::$services = [];
        self::$singletons = [];
    }

    public function index(): string
    {
        self::$requests[] = $this->request;
        self::$containers[] = $this->container;
        self::$services[] = $this->service;
        self::$singletons[] = $this->singleton;

        return 'request-scope';
    }
}

final class RequestScopeThrowingController
{
    public static ?\Lemonade\Framework\Container\ContainerInterface $container = null;

    public function __construct(\Lemonade\Framework\Container\ContainerInterface $container)
    {
        self::$container = $container;
    }

    public function index(): never
    {
        throw new \RuntimeException('Request scope controller failure.');
    }
}

namespace Lemonade\Framework\Tests\Unit\Core;

final class KernelScopeTrackingContainer implements \Lemonade\Framework\Container\ContainerInterface, \Lemonade\Framework\Container\ScopeFactoryInterface
{
    public ?\Lemonade\Framework\Container\ScopedContainerInterface $lastScope = null;

    public function __construct(
        private readonly \Lemonade\Framework\Container\Container $delegate,
    ) {}

    public function beginScope(\Lemonade\Framework\Container\ScopeKind $kind): \Lemonade\Framework\Container\ScopedContainerInterface
    {
        return $this->lastScope = $this->delegate->beginScope($kind);
    }

    public function set(string $id, callable|object|string $concrete): void
    {
        $this->delegate->set($id, $concrete);
    }

    public function singleton(string $id, callable|object|string $concrete): void
    {
        $this->delegate->singleton($id, $concrete);
    }

    public function scoped(string $id, callable|object|string $concrete): void
    {
        $this->delegate->scoped($id, $concrete);
    }

    public function singletonTagged(string $id, callable|object|string $concrete, string ...$tags): void
    {
        $this->delegate->singletonTagged($id, $concrete, ...$tags);
    }

    public function tag(string $serviceId, string $tag): void
    {
        $this->delegate->tag($serviceId, $tag);
    }

    public function tagged(string $tag): iterable
    {
        return $this->delegate->tagged($tag);
    }

    public function setDiagnosticLogger(?\Psr\Log\LoggerInterface $logger): void
    {
        $this->delegate->setDiagnosticLogger($logger);
    }

    public function has(string $id): bool
    {
        return $this->delegate->has($id);
    }

    public function isBound(string $id): bool
    {
        return $this->delegate->isBound($id);
    }

    public function get(string $id): mixed
    {
        return $this->delegate->get($id);
    }
}

final class KernelRouteRegistrarProvider implements \Lemonade\Framework\Core\ServiceProviderInterface
{
    public function register(\Lemonade\Framework\Container\ContainerInterface $container): void
    {
        $container->singletonTagged(
            KernelRouteRegistrar::class,
            KernelRouteRegistrar::class,
            \Lemonade\Framework\Routing\RouteRegistrarInterface::class,
        );
    }
}

final class KernelRequestProbeProvider implements \Lemonade\Framework\Core\ServiceProviderInterface
{
    public static ?\Psr\Http\Message\ServerRequestInterface $request = null;

    public function register(\Lemonade\Framework\Container\ContainerInterface $container): void
    {
        if (!$container->isBound(\Psr\Http\Message\ServerRequestInterface::class)) {
            return;
        }

        self::$request = $container->get(\Psr\Http\Message\ServerRequestInterface::class);
    }
}

final class KernelBootDependencyProvider implements \Lemonade\Framework\Core\DefinitionServiceProviderInterface
{
    public function register(\Lemonade\Framework\Container\ContainerBuilderInterface $builder): void
    {
        $builder->singleton(KernelBootDependency::class, KernelBootDependency::class);
    }
}

final class KernelBootObserverProvider implements \Lemonade\Framework\Core\BootableServiceProviderInterface
{
    public static bool $resolved = false;

    public function boot(\Lemonade\Framework\Container\ContainerInterface $container): void
    {
        self::$resolved = $container->get(KernelBootDependency::class) instanceof KernelBootDependency;
    }
}

final class KernelBootDependency {}

final class RequestScopeKernelService {}

final class RequestScopeKernelSingleton {}

final class KernelScopeCaptureMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    public static ?\Lemonade\Framework\Container\ContainerInterface $container = null;

    public function __construct(\Lemonade\Framework\Container\ContainerInterface $container)
    {
        self::$container = $container;
    }

    public static function reset(): void
    {
        self::$container = null;
    }

    public function process(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Server\RequestHandlerInterface $handler,
    ): \Psr\Http\Message\ResponseInterface {
        return $handler->handle($request);
    }
}

final class KernelThrowingScopeMiddleware implements \Psr\Http\Server\MiddlewareInterface
{
    public static ?\Lemonade\Framework\Container\ContainerInterface $container = null;

    public function __construct(\Lemonade\Framework\Container\ContainerInterface $container)
    {
        self::$container = $container;
    }

    public function process(
        \Psr\Http\Message\ServerRequestInterface $request,
        \Psr\Http\Server\RequestHandlerInterface $handler,
    ): \Psr\Http\Message\ResponseInterface {
        unset($request, $handler);
        throw new \RuntimeException('Request scope middleware failure.');
    }
}

final class KernelRouteRegistrar implements \Lemonade\Framework\Routing\RouteRegistrarInterface
{
    public function id(): string
    {
        return 'test.kernel-provider';
    }

    public function priority(): int
    {
        return 10;
    }

    public function registerRoutes(\Lemonade\Framework\Routing\Router $router): void
    {
        if (!$router->hasExplicitRouteForPath('GET', '/application-phase')) {
            throw new \RuntimeException('Application routes must register before provider routes.');
        }

        $router->get('/provider-phase', 'KernelRouteRegistrarSupportController@provider');
    }
}
