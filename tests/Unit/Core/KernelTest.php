<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\Kernel;
use Lemonade\Framework\Http\Middleware\MiddlewareResolver;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Http\Psr\ResponseEmitter;
use Lemonade\Framework\Routing\Exception\RouteNotFoundException;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private string $root;

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

    public function testBootstrapSkipsMissingConventionalConfigFiles(): void
    {
        $configDir = $this->root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config';
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Localization.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Cache.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Logging.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Session.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Database.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Breadcrumbs.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Upload.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Api.php');
        @unlink($configDir . DIRECTORY_SEPARATOR . 'Providers.php');

        $kernel = $this->kernel(false);
        $response = $kernel->run(new ServerRequest('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testKernelConventionalConfigDoesNotIncludeCommandsPhp(): void
    {
        $this->writeConfigFile(
            'Commands.php',
            "<?php\n\ndeclare(strict_types=1);\n\nthrow new \\RuntimeException('Commands config should not be loaded by HTTP kernel');\n",
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

    public function testHandleCreatesRequestFromGlobalsWhenNullProvided(): void
    {
        $this->writeRoutingHeadFallbackTarget();
        $kernel = $this->kernel(true);

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

    private function kernel(bool $debug): Kernel
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            $debug ? DebugMode::enabled() : DebugMode::disabled(),
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

        $defaults = [
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
        ];

        foreach ($defaults as $file) {
            if ($file === 'Config.php') {
                $this->writeConfigFile(
                    'Config.php',
                    "<?php\n\ndeclare(strict_types=1);\n\nreturn ['shared' => ['App.php' => null, 'Localization.php' => null, 'Cache.php' => null, 'Logging.php' => null, 'Session.php' => null, 'Database.php' => null, 'Breadcrumbs.php' => null, 'Upload.php' => null, 'Api.php' => 'api', 'Providers.php' => null], 'http' => [], 'cli' => ['Commands.php' => null]];\n",
                );
                continue;
            }

            $this->writeConfigFile($file, "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
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

    private function writeThrowingConfig(string $exceptionClass, string $message): void
    {
        $this->writeConfigFile(
            'App.php',
            "<?php\n\ndeclare(strict_types=1);\n\nthrow new {$exceptionClass}('" . addslashes($message) . "');\n",
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
