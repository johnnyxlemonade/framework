<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Logging\Config\LoggingChannelConfig;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogFilePathResolver;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Filesystem\Contract\DirectoryManagerInterface;
use Lemonade\Framework\Http\Config\ErrorConfig;
use Lemonade\Framework\Http\Error\ErrorPageRenderer;
use Lemonade\Framework\Http\Exception\NotFoundHttpException;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Http\Middleware\ErrorHandlingMiddleware;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\View\View;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class ErrorHandlingMiddlewareTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-error-middleware-' . uniqid('', true);
        $this->writeErrorViews();
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->root);
    }

    public function testSuccessfulRequestDoesNotResolveView(): void
    {
        $factory = new Psr17Factory();
        $view = new View($this->viewsPath());
        $container = new TrackingViewContainer($view);
        $middleware = $this->middleware(
            container: $container,
            errorLogNotFound: false,
        );
        $handler = new ErrorMiddlewareRecordingHandler(
            $factory->createResponse(200)->withBody($factory->createStream('ok')),
        );

        $response = $middleware->process(
            $factory->createServerRequest('GET', '/ok'),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $container->viewResolutions);
    }

    public function testNotFoundReturns404WithoutErrorLevelLogByDefault(): void
    {
        $factory = new Psr17Factory();
        $view = new View($this->viewsPath());
        $container = new TrackingViewContainer($view);
        $middleware = $this->middleware(
            container: $container,
            errorLogNotFound: false,
        );
        $handler = new ErrorMiddlewareThrowableHandler(
            NotFoundHttpException::create('Unsupported locale "dsadsa".'),
        );

        $response = $middleware->process(
            $factory->createServerRequest('GET', '/dsadsa'),
            $handler,
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<main class="error-layout">', (string) $response->getBody());
        self::assertStringContainsString('Rendered 404 template', (string) $response->getBody());
        self::assertSame(1, $container->viewResolutions);
        self::assertSame([], $this->readErrorLogRecords());
    }

    public function testNotFoundCanBeLoggedWithoutTraceAndWithoutErrorLevel(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware(
            container: new TrackingViewContainer(new View($this->viewsPath())),
            errorLogNotFound: true,
        );
        $handler = new ErrorMiddlewareThrowableHandler(
            NotFoundHttpException::create('Unsupported locale "dsadsa".'),
        );

        $response = $middleware->process(
            $factory->createServerRequest('GET', '/dsadsa'),
            $handler,
        );

        $records = $this->readErrorLogRecords();

        self::assertSame(404, $response->getStatusCode());
        self::assertCount(1, $records);
        self::assertSame('notice', $records[0]['level'] ?? null);
        self::assertSame('Unsupported locale "dsadsa".', $records[0]['message'] ?? null);
        self::assertIsArray($records[0]['context'] ?? null);
        self::assertSame(404, $records[0]['context']['status'] ?? null);
        self::assertArrayNotHasKey('trace', $records[0]['context']);
        self::assertArrayNotHasKey('file', $records[0]['context']);
        self::assertArrayNotHasKey('line', $records[0]['context']);
    }

    public function testRuntimeExceptionReturns500AndWritesErrorLogWithExceptionContext(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware(
            container: new TrackingViewContainer(new View($this->viewsPath())),
            errorLogNotFound: false,
        );
        $handler = new ErrorMiddlewareThrowableHandler(
            new RuntimeException('Unexpected failure.'),
        );

        $response = $middleware->process(
            $factory->createServerRequest('GET', '/broken'),
            $handler,
        );

        $records = $this->readErrorLogRecords();

        self::assertSame(500, $response->getStatusCode());
        self::assertCount(1, $records);
        self::assertSame('error', $records[0]['level'] ?? null);
        self::assertSame('Unexpected failure.', $records[0]['message'] ?? null);
        self::assertIsArray($records[0]['context'] ?? null);
        self::assertSame(RuntimeException::class, $records[0]['context']['exception'] ?? null);
        self::assertSame('Unexpected failure.', $records[0]['context']['message'] ?? null);
        self::assertIsString($records[0]['context']['trace'] ?? null);
        self::assertNotSame('', $records[0]['context']['trace'] ?? '');
    }

    public function testHtml404UsesErrorPageRendererTemplateFlow(): void
    {
        $factory = new Psr17Factory();
        $middleware = $this->middleware(
            container: new TrackingViewContainer(new View($this->viewsPath())),
            errorLogNotFound: false,
        );
        $handler = new ErrorMiddlewareThrowableHandler(
            NotFoundHttpException::create('Missing page.'),
        );

        $response = $middleware->process(
            $factory->createServerRequest('GET', '/missing'),
            $handler,
        );

        $body = (string) $response->getBody();

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('<main class="error-layout">', $body);
        self::assertStringContainsString('Rendered 404 template', $body);
    }

    private function middleware(
        TrackingViewContainer $container,
        bool $errorLogNotFound,
    ): ErrorHandlingMiddleware {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );

        return new ErrorHandlingMiddleware(
            config: new LoggingConfig(
                app: new LoggingChannelConfig(true, 'app.log', 'info', 7),
                error: new LoggingChannelConfig(true, 'error.log', 'error', 7),
                request: new LoggingChannelConfig(false, 'request.log', 'info', 7),
                benchmark: new LoggingChannelConfig(false, 'benchmark.log', 'debug', 7),
                requestMinStatus: 0,
                errorLogNotFound: $errorLogNotFound,
            ),
            responseFactory: new Psr17Factory(),
            logs: new LogManager(
                config: new LoggingConfig(
                    app: new LoggingChannelConfig(true, 'app.log', 'info', 7),
                    error: new LoggingChannelConfig(true, 'error.log', 'error', 7),
                    request: new LoggingChannelConfig(false, 'request.log', 'info', 7),
                    benchmark: new LoggingChannelConfig(false, 'benchmark.log', 'debug', 7),
                    requestMinStatus: 0,
                    errorLogNotFound: $errorLogNotFound,
                ),
                pathResolver: new LogFilePathResolver($context),
                directoryManager: new ErrorMiddlewareDirectoryManager(),
            ),
            httpLogContext: new HttpLogContext(new HttpRequestInspector()),
            errorPageRenderer: new ErrorPageRenderer(
                context: $context,
                config: new ErrorConfig('errors/404', 'errors/500'),
                container: $container,
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readErrorLogRecords(): array
    {
        $logDir = $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir)) {
            return [];
        }

        $files = glob($logDir . DIRECTORY_SEPARATOR . 'error-*.log');
        if ($files === false || $files === []) {
            return [];
        }

        sort($files);
        $contents = file_get_contents($files[0]);
        if (!is_string($contents) || trim($contents) === '') {
            return [];
        }

        $records = [];

        $lines = preg_split('/\R/', trim($contents));

        foreach (is_array($lines) ? $lines : [] as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                $records[] = $decoded;
            }
        }

        return $records;
    }

    private function writeErrorViews(): void
    {
        $layoutDir = $this->viewsPath() . DIRECTORY_SEPARATOR . 'layouts';
        $errorDir = $this->viewsPath() . DIRECTORY_SEPARATOR . 'errors';

        @mkdir($layoutDir, 0775, true);
        @mkdir($errorDir, 0775, true);

        file_put_contents(
            $layoutDir . DIRECTORY_SEPARATOR . 'error.php',
            '<main class="error-layout"><?= $content ?></main>',
        );
        file_put_contents(
            $errorDir . DIRECTORY_SEPARATOR . '404.php',
            '<article>Rendered 404 template</article>',
        );
        file_put_contents(
            $errorDir . DIRECTORY_SEPARATOR . '500.php',
            '<article>Rendered 500 template</article>',
        );
    }

    private function viewsPath(): string
    {
        return $this->root . DIRECTORY_SEPARATOR . 'views';
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

final class TrackingViewContainer implements ContainerInterface
{
    public int $viewResolutions = 0;

    public function __construct(
        private readonly View $view,
    ) {}

    public function set(string $id, callable|object|string $concrete): void
    {
        unset($id, $concrete);
    }

    public function singleton(string $id, callable|object|string $concrete): void
    {
        unset($id, $concrete);
    }

    public function setDiagnosticLogger(?\Psr\Log\LoggerInterface $logger): void
    {
        unset($logger);
    }

    public function has(string $id): bool
    {
        return $id === View::class;
    }

    public function isBound(string $id): bool
    {
        return $id === View::class;
    }

    public function get(string $id): mixed
    {
        if ($id !== View::class) {
            throw new \RuntimeException('Unexpected service request: ' . $id);
        }

        $this->viewResolutions++;

        return $this->view;
    }
}

final class ErrorMiddlewareDirectoryManager implements DirectoryManagerInterface
{
    public function create(string $path, int $mode = 0775): void
    {
        if (!is_dir($path)) {
            mkdir($path, $mode, true);
        }
    }

    public function delete(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
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

            $this->delete($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }

    public function copy(string $src, string $dst, bool $overwrite = false): void
    {
        unset($src, $dst, $overwrite);

        throw new \BadMethodCallException('Not needed in this test.');
    }

    public function write(string $file, string $data, ?int $mode = 0666): void
    {
        unset($data, $mode);

        $directory = dirname($file);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($file, '');
    }

    public function stream(string $path, bool $recursive = true): \Generator
    {
        unset($path, $recursive);
        yield from [];
    }

    public function tree(string $path, bool $recursive = true): \Generator
    {
        unset($path, $recursive);
        yield from [];
    }

    public function find(string $pattern, string $path): \Generator
    {
        unset($pattern, $path);
        yield from [];
    }
}

final class ErrorMiddlewareRecordingHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseInterface $response,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        return $this->response;
    }
}

final class ErrorMiddlewareThrowableHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly \Throwable $throwable,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        throw $this->throwable;
    }
}
