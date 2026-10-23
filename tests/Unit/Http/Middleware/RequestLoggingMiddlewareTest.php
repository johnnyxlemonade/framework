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

    public function testRequestLoggingUsesOnlySafeAllowlistedHttpMetadata(): void
    {
        $factory = new Psr17Factory();
        $request = $factory
            ->createServerRequest(
                'POST',
                'https://example.test/account/profile?token=query-secret&password=query-password',
            )
            ->withHeader('Authorization', 'Bearer authorization-secret')
            ->withHeader('Cookie', 'session=cookie-secret')
            ->withHeader('Referer', 'https://example.test/login?api_key=referer-secret')
            ->withHeader('User-Agent', 'user-agent-secret')
            ->withHeader('X-Request-Id', 'request-123')
            ->withParsedBody([
                'password' => 'form-secret',
            ])
            ->withBody($factory->createStream('raw-body-secret'));

        $response = $this->middleware(new LoggingConfig(7, true, 0, false))->process(
            $request,
            new RequestLoggingResponseHandler(
                status: 201,
                headers: [
                    'Set-Cookie' => 'session=response-cookie-secret',
                    'X-Diagnostic-Token' => 'response-header-secret',
                ],
            ),
        );

        self::assertSame(201, $response->getStatusCode());

        $records = $this->readRequestLogRecords();
        self::assertCount(1, $records);
        self::assertSame('http.request', $records[0]['message'] ?? null);
        self::assertSame('POST', $records[0]['context']['method'] ?? null);
        self::assertSame('/account/profile', $records[0]['context']['path'] ?? null);
        self::assertSame('request-123', $records[0]['context']['request_id'] ?? null);
        self::assertSame(201, $records[0]['context']['status'] ?? null);
        self::assertIsInt($records[0]['context']['duration_ms'] ?? null);
        self::assertSame(
            ['method', 'path', 'request_id', 'status', 'duration_ms'],
            array_keys($records[0]['context']),
        );

        $serialized = json_encode($records, JSON_THROW_ON_ERROR);
        foreach ([
            'query-secret',
            'query-password',
            'authorization-secret',
            'cookie-secret',
            'referer-secret',
            'user-agent-secret',
            'form-secret',
            'raw-body-secret',
            'response-cookie-secret',
            'response-header-secret',
        ] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
    }

    private function middleware(LoggingConfig $config): RequestLoggingMiddleware
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/public'),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function readRequestLogRecords(): array
    {
        $contents = file_get_contents($this->requestLogFile());
        self::assertIsString($contents);

        $records = [];
        $lines = preg_split('/\R/', trim($contents));

        foreach (is_array($lines) ? $lines : [] as $line) {
            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            $records[] = $decoded;
        }

        return $records;
    }
}

final class RequestLoggingResponseHandler implements RequestHandlerInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly array $headers = [],
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        $response = (new Psr17Factory())->createResponse($this->status);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
