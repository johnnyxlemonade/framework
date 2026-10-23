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
use Lemonade\Framework\Http\Middleware\BenchmarkMiddleware;
use Lemonade\Framework\Http\Request\HttpRequestInspector;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkResponseInjector;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class BenchmarkMiddlewareSecurityTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = rtrim(sys_get_temp_dir(), '/\\')
            . DIRECTORY_SEPARATOR
            . 'lemonade-benchmark-logging-'
            . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            (new DirectoryManager())->delete($this->root);
        }
    }

    public function testBenchmarkLogExcludesHttpInputExceptionDetailsAndDatabaseValues(): void
    {
        $factory = new Psr17Factory();
        $context = new ApplicationContext(
            Environment::Development,
            new Path($this->root),
            DebugMode::enabled(),
        );
        $config = new LoggingConfig(7, false, 0, true);
        $benchmark = new Benchmark();
        $run = $benchmark->start();
        $run->mark('mark-name-secret');
        $run->recordDatabaseQuery(
            'SELECT * FROM users WHERE api_key = "sql-secret"',
            ['binding-secret'],
            1.25,
            true,
        );
        $middleware = new BenchmarkMiddleware(
            benchmark: $benchmark,
            config: $config,
            logs: new LogManager(
                config: $config,
                pathResolver: new LogFilePathResolver($context),
                directoryManager: new DirectoryManager(),
            ),
            injector: new BenchmarkResponseInjector(
                new BenchmarkConfig(false),
                $context,
            ),
            httpLogContext: new HttpLogContext(new HttpRequestInspector()),
        );
        $request = $factory
            ->createServerRequest('POST', 'https://example.test/jobs/run?api_key=query-secret')
            ->withHeader('Authorization', 'Bearer authorization-secret')
            ->withHeader('Cookie', 'session=cookie-secret')
            ->withHeader('X-Request-Id', 'benchmark-123')
            ->withParsedBody([
                'password' => 'form-secret',
            ])
            ->withBody($factory->createStream('raw-body-secret'));

        try {
            $middleware->process(
                $request,
                new BenchmarkLoggingThrowableHandler(
                    new RuntimeException('exception-message-secret'),
                ),
            );
            self::fail('The benchmark handler exception should have been rethrown.');
        } catch (RuntimeException $exception) {
            self::assertSame('exception-message-secret', $exception->getMessage());
        }

        $records = $this->readBenchmarkLogRecords();
        self::assertCount(1, $records);
        self::assertSame('request.benchmark', $records[0]['message'] ?? null);
        self::assertSame('POST', $records[0]['context']['context']['method'] ?? null);
        self::assertSame('/jobs/run', $records[0]['context']['context']['path'] ?? null);
        self::assertSame('benchmark-123', $records[0]['context']['context']['request_id'] ?? null);
        self::assertSame(500, $records[0]['context']['status'] ?? null);
        self::assertSame(RuntimeException::class, $records[0]['context']['exception_class'] ?? null);
        self::assertSame(1, $records[0]['context']['database']['query_count'] ?? null);
        self::assertArrayNotHasKey('queries', $records[0]['context']['database']);

        $serialized = json_encode($records, JSON_THROW_ON_ERROR);
        foreach ([
            'query-secret',
            'authorization-secret',
            'cookie-secret',
            'form-secret',
            'raw-body-secret',
            'exception-message-secret',
            'sql-secret',
            'binding-secret',
            'mark-name-secret',
        ] as $secret) {
            self::assertStringNotContainsString($secret, $serialized);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readBenchmarkLogRecords(): array
    {
        $path = $this->root
            . DIRECTORY_SEPARATOR
            . 'storage'
            . DIRECTORY_SEPARATOR
            . 'writable'
            . DIRECTORY_SEPARATOR
            . 'logs'
            . DIRECTORY_SEPARATOR
            . 'benchmark-'
            . date('Y-m-d')
            . '.log';
        $contents = file_get_contents($path);
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

final readonly class BenchmarkLoggingThrowableHandler implements RequestHandlerInterface
{
    public function __construct(
        private RuntimeException $exception,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        throw $this->exception;
    }
}
