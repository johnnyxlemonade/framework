<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Middleware;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Logging\Config\LoggingConfig;
use Lemonade\Framework\Core\Logging\LogFilePathResolver;
use Lemonade\Framework\Core\Logging\LogManager;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Http\Logging\HttpLogContext;
use Lemonade\Framework\Http\Middleware\RequestLoggingMiddleware;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestLoggingMiddlewareTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'lemonade-request-logging-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            (new DirectoryManager())->delete($this->root);
        }
    }

    public function testDisabledRequestLoggingDoesNotCreateAFile(): void
    {
        $response = $this->middleware(new LoggingConfig(7, false, 0, false))->process(
            (new Psr17Factory())->createServerRequest('GET', '/missing'),
            new RequestLoggingResponseHandler(404),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertFileDoesNotExist($this->requestLogFile());
    }

    public function testRequestLoggingRecords404WhenMinStatusIncludesIt(): void
    {
        $response = $this->middleware(new LoggingConfig(7, true, 400, false))->process(
            (new Psr17Factory())->createServerRequest('GET', '/missing'),
            new RequestLoggingResponseHandler(404),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertFileExists($this->requestLogFile());
    }

    public function testRequestLoggingSkips404WhenMinStatusIs500(): void
    {
        $response = $this->middleware(new LoggingConfig(7, true, 500, false))->process(
            (new Psr17Factory())->createServerRequest('GET', '/missing'),
            new RequestLoggingResponseHandler(404),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertFileDoesNotExist($this->requestLogFile());
    }

    private function middleware(LoggingConfig $config): RequestLoggingMiddleware
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root),
            DebugMode::disabled(),
        );

        return new RequestLoggingMiddleware(
            config: $config,
            logs: new LogManager(
                config: $config,
                pathResolver: new LogFilePathResolver($context),
                directoryManager: new DirectoryManager(),
            ),
            context: new HttpLogContext(new HttpRequestInspector()),
        );
    }

    private function requestLogFile(): string
    {
        return $this->root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'writable'
            . DIRECTORY_SEPARATOR
            . 'logs'
            . DIRECTORY_SEPARATOR
            . 'request-'
            . date('Y-m-d')
            . '.log';
    }
}

final class RequestLoggingResponseHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly int $status,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        return (new Psr17Factory())->createResponse($this->status);
    }
}
