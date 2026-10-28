<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Integration\Http;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Framework;
use Lemonade\Framework\Core\Kernel;
use Lemonade\Framework\Core\KernelFactory;
use Lemonade\Framework\Core\Logging\Config\LoggingConfigDefinition;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Instrumentation\DatabaseObserverInterface;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseMeasurements;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Http\HttpServiceProvider;
use Lemonade\Framework\Http\Middleware\MiddlewareStack;
use Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfigDefinition;
use Lemonade\Framework\Observability\Http\HttpCheckpoint;
use Lemonade\Framework\Observability\Http\HttpDiagnosticsObserverFactoryInterface;
use Lemonade\Framework\Observability\Http\HttpDiagnosticsObserverInterface;
use Lemonade\Framework\Observability\Http\HttpMetrics;
use Lemonade\Framework\Observability\Http\Internal\HttpInstrumentation;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\RouteMatch;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class HttpDiagnosticsLifecycleTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-http-metrics-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/app/Config', 0700, true);
        mkdir($this->root . '/public', 0700, true);
        file_put_contents($this->root . '/app/Config/Config.yaml', "shared: []\nhttp: []\ncli: []\n");
        file_put_contents($this->root . '/app/Config/Routing.php', '<?php return static function ($router): void {};');
        DiagnosticsScopeCleanup::$closed = false;
    }

    protected function tearDown(): void
    {
        (new DirectoryManager())->delete($this->root);
    }

    public function testMetricsIncludeBootstrapMiddlewareUnwindAndScopeCleanupAcrossRequests(): void
    {
        $factory = new RecordingHttpFactory();
        $kernel = $this->kernel($factory);
        $container = $kernel->container();
        self::assertInstanceOf(Container::class, $container);
        $connection = $this->connection($container);
        $container->singleton(PdoConnection::class, $connection);
        $container->scoped(DiagnosticsScopeCleanup::class, DiagnosticsScopeCleanup::class);
        $container->scoped(DiagnosticsMiddleware::class, DiagnosticsMiddleware::class);
        $kernel->framework()->middleware(static function (MiddlewareStack $stack): void {
            $stack->prepend(DiagnosticsMiddleware::class);
        });
        $kernel->container()->get(\Lemonade\Framework\Routing\Router::class)->getNamed('diagnostics.page', '/page', ControllerAction::for(DiagnosticsController::class, 'page'));
        // Routing registration occurs during bootstrap, before dispatch.
        file_put_contents($this->root . '/app/Config/Routing.php', <<<'ROUTES'
<?php
return static function ($router): void {
    $GLOBALS['diagnostics_bootstrap_connection']->select('SELECT 1');
};
ROUTES);
        $GLOBALS['diagnostics_bootstrap_connection'] = $connection;
        try {
            $response = $kernel->run(new ServerRequest('GET', '/page'));
        } finally {
            unset($GLOBALS['diagnostics_bootstrap_connection']);
        }
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('unchanged body', (string) $response->getBody());
        self::assertTrue(DiagnosticsScopeCleanup::$closed);
        $first = $factory->captures[0];
        self::assertNotNull($first->metrics);
        self::assertTrue($first->scopeClosedAtCompletion);
        self::assertSame(4, $first->metrics->sqlCount());
        self::assertCount(4, $first->queries);
        self::assertSame(array_sum($first->queries), $first->metrics->sqlMs());
        self::assertGreaterThanOrEqual(2.0, $first->metrics->durationMs());
        self::assertSame('diagnostics.page', $first->match?->name());
        self::assertContains(HttpCheckpoint::ConfigurationLoaded, $first->checkpoints);
        self::assertContains(HttpCheckpoint::RoutesRegistered, $first->checkpoints);
        self::assertContains(HttpCheckpoint::RouteMatchStarted, $first->checkpoints);
        self::assertContains(HttpCheckpoint::ControllerResolved, $first->checkpoints);
        self::assertContains(HttpCheckpoint::ControllerActionFinished, $first->checkpoints);
        self::assertContains(HttpCheckpoint::ResponseCreated, $first->checkpoints);
        self::assertNull($container->get(HttpInstrumentation::class)->observer());
        self::assertFalse($response->hasHeader('X-Lemonade-Time-Ms'));

        self::assertSame(200, $kernel->run(new ServerRequest('GET', '/page'))->getStatusCode());
        $second = $factory->captures[1];
        self::assertNotNull($second->metrics);
        self::assertNotSame($first->metrics, $second->metrics);
        self::assertSame(3, $second->metrics->sqlCount());
        self::assertNotContains(HttpCheckpoint::BootstrapStarted, $second->checkpoints);
        $connection->select('SELECT 1');
        self::assertCount(3, $second->queries);
        self::assertSame(4, $first->metrics->sqlCount());
    }

    public function testHealthFailureAndMissingRouteEachCompleteAndReleaseCapture(): void
    {
        $factory = new RecordingHttpFactory();
        $kernel = $this->kernel($factory);
        $kernel->container()->get(\Lemonade\Framework\Routing\Router::class)->get('/failure', ControllerAction::for(DiagnosticsController::class, 'failure'));
        foreach ([['/api/framework/health', 200], ['/failure', 500], ['/missing', 404]] as [$path, $status]) {
            $response = $kernel->run(new ServerRequest('GET', $path));
            self::assertSame($status, $response->getStatusCode());
            self::assertNull($kernel->container()->get(HttpInstrumentation::class)->observer());
        }
        self::assertCount(3, $factory->captures);
        foreach ($factory->captures as $capture) {
            self::assertNotNull($capture->metrics);
            self::assertSame(1, $capture->completedCount);
            self::assertSame(0, $capture->metrics->sqlCount());
        }
        self::assertNotContains(HttpCheckpoint::BootstrapStarted, $factory->captures[0]->checkpoints);
        self::assertNotContains(HttpCheckpoint::ControllerActionFinished, $factory->captures[1]->checkpoints);
        $db = $kernel->container()->get(DatabaseInstrumentation::class);
        $db->collect(static function (DatabaseMeasurements $measurements): void {
            self::assertSame(0, $measurements->queryCount());
        });
    }

    #[DataProvider('responseModes')]
    public function testExplicitHeadersAreIndependentOfEnvironmentAndPreserveBody(bool $enabled, string $type, string $body): void
    {
        $kernel = $this->kernel();
        $kernel->framework()->config(HttpDiagnosticsConfigDefinition::create()->enabled($enabled));
        $response = new Response(200, ['Content-Type' => $type, 'ETag' => 'original'], $body);
        $container = $kernel->container();
        self::assertInstanceOf(Container::class, $container);
        $container->singleton(DiagnosticsTerminal::class, new DiagnosticsTerminal($response));
        $kernel->framework()->middleware(static function (MiddlewareStack $stack): void {
            $stack->prepend(DiagnosticsTerminal::class);
        });
        $actual = $kernel->run(new ServerRequest('GET', '/body'));
        self::assertSame($enabled, $container->get(\Lemonade\Framework\Observability\Http\Config\HttpDiagnosticsConfig::class)->enabled());
        self::assertSame($response->getBody(), $actual->getBody());
        self::assertSame($body, (string) $actual->getBody());
        self::assertSame('original', $actual->getHeaderLine('ETag'));
        foreach (['Time-Ms', 'PHP-Peak-Bytes', 'SQL-Count', 'SQL-Time-Ms'] as $header) {
            self::assertSame($enabled, $actual->hasHeader('X-Lemonade-' . $header));
        }
        if (!$enabled) {
            self::assertSame($response, $actual);
        }
    }

    /** @return iterable<string, array{bool, string, string}> */
    public static function responseModes(): iterable
    {
        foreach ([false, true] as $enabled) {
            foreach (['text/html' => '<html><body>unchanged</body></html>', 'application/json' => '{"ok":true}', 'application/octet-stream' => "\x00\x01binary"] as $type => $body) {
                yield (int) $enabled . '-' . $type => [$enabled, $type, $body];
            }
        }
    }

    public function testRequestLogUsesCompletedDurationAndLoggingAloneDoesNotActivateSql(): void
    {
        $factory = new RecordingHttpFactory();
        $kernel = $this->kernel($factory);
        $kernel->framework()->config(LoggingConfigDefinition::create()->requestEnabled()->requestMinStatus(400));
        $kernel->run(new ServerRequest('GET', '/missing?token=secret'));
        $metrics = $factory->captures[0]->metrics;
        self::assertNotNull($metrics);
        $file = $this->root . '/storage/writable/logs/request-' . date('Y-m-d') . '.log';
        $contents = file_get_contents($file);
        self::assertIsString($contents);
        $record = json_decode(trim($contents), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame((int) round($metrics->durationMs()), $record['context']['duration_ms']);
        self::assertStringNotContainsString('secret', $contents);

        $loggingOnly = $this->kernel();
        $loggingOnly->framework()->config(LoggingConfigDefinition::create()->requestEnabled());
        $container = $loggingOnly->container();
        self::assertInstanceOf(Container::class, $container);
        $container->singleton(DiagnosticsInactiveSqlProbe::class, new DiagnosticsInactiveSqlProbe($container->get(DatabaseInstrumentation::class)));
        $loggingOnly->framework()->middleware(static function (MiddlewareStack $stack): void {
            $stack->prepend(DiagnosticsInactiveSqlProbe::class);
        });
        // An overlapping SQL capture would throw if logging had silently enabled SQL.
        self::assertSame(202, $loggingOnly->run(new ServerRequest('GET', '/probe'))->getStatusCode());
    }

    #[DataProvider('observerFailures')]
    public function testDiagnosticsFailuresPreserveResponseAndReleaseState(string $stage): void
    {
        $factory = new RecordingHttpFactory($stage);
        $kernel = $this->kernel($factory);
        $kernel->framework()->config(HttpDiagnosticsConfigDefinition::create()->enabled());
        foreach ([1, 2] as $attempt) {
            $response = $kernel->run(new ServerRequest('GET', '/missing'));
            self::assertSame(404, $response->getStatusCode(), (string) $response->getBody());
            self::assertTrue($response->hasHeader('X-Lemonade-Time-Ms'));
            self::assertNull($kernel->container()->get(HttpInstrumentation::class)->observer());
        }
    }

    /** @return iterable<string, array{string}> */
    public static function observerFailures(): iterable
    {
        foreach (['factory', 'checkpoint', 'completed', 'response'] as $stage) {
            yield $stage => [$stage];
        }
    }

    public function testStandaloneFrameworkOwnsTheSameMeasurementBoundary(): void
    {
        $factory = new RecordingHttpFactory();
        $container = new Container();
        $framework = new Framework($container, $this->context(), diagnostics: $factory);
        $framework->register(new \Lemonade\Framework\Core\CoreServiceProvider());
        $framework->register(new \Lemonade\Framework\Filesystem\FilesystemServiceProvider());
        $framework->register(new \Lemonade\Framework\Core\Logging\LoggingServiceProvider());
        $framework->register(new HttpServiceProvider());
        $framework->config(HttpDiagnosticsConfigDefinition::create()->enabled());
        $container->get(\Lemonade\Framework\Routing\Router::class)->get('/page', ControllerAction::for(DiagnosticsController::class, 'page'));
        foreach ([1, 2] as $attempt) {
            $response = $framework->run(new ServerRequest('GET', '/page'));
            self::assertSame(200, $response->getStatusCode());
            self::assertTrue($response->hasHeader('X-Lemonade-Time-Ms'));
            self::assertNull($container->get(HttpInstrumentation::class)->observer());
        }
        self::assertCount(2, $factory->captures);
        self::assertNotSame($factory->captures[0]->metrics, $factory->captures[1]->metrics);
    }

    public function testStandaloneEscapingFailureCompletesMetricsAndPreservesException(): void
    {
        $factory = new RecordingHttpFactory();
        $container = new Container();
        $framework = new Framework($container, $this->context(), diagnostics: $factory);
        $framework->register(new \Lemonade\Framework\Core\CoreServiceProvider());
        $container->get(\Lemonade\Framework\Routing\Router::class)->get('/failure', ControllerAction::for(DiagnosticsController::class, 'failure'));
        try {
            $framework->run(new ServerRequest('GET', '/failure'));
            self::fail('Unconverted standalone failure must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('application failure', $exception->getMessage());
        }
        self::assertNotNull($factory->captures[0]->metrics);
        self::assertSame(1, $factory->captures[0]->completedCount);
        self::assertNull($container->get(HttpInstrumentation::class)->observer());
    }

    private function context(): ApplicationContext
    {
        return new ApplicationContext(Environment::Production, new Path($this->root), DebugMode::disabled());
    }

    private function kernel(?RecordingHttpFactory $factory = null): Kernel
    {
        return (new KernelFactory(diagnostics: $factory))->create($this->context());
    }

    private function connection(Container $container): PdoConnection
    {
        return new PdoConnection(
            DatabaseConfig::fromArray(['driver' => 'pdo', 'dsn' => 'sqlite::memory:']),
            $container->get(DatabaseInstrumentation::class),
        );
    }
}

final class RecordingHttpFactory implements HttpDiagnosticsObserverFactoryInterface
{
    /** @var list<RecordingHttpCapture> */
    public array $captures = [];

    public function __construct(private readonly string $failure = '')
    {
    }

    public function create(ContainerInterface $container, ServerRequestInterface $request, int|float $startedNs): HttpDiagnosticsObserverInterface
    {
        if ($this->failure === 'factory') {
            throw new RuntimeException('diagnostics unavailable');
        }
        return $this->captures[] = new RecordingHttpCapture($this->failure);
    }
}

final class RecordingHttpCapture implements HttpDiagnosticsObserverInterface, DatabaseObserverInterface
{
    public ?HttpMetrics $metrics = null;
    public ?RouteMatch $match = null;
    public int $completedCount = 0;
    public bool $scopeClosedAtCompletion = false;
    /** @var list<HttpCheckpoint> */
    public array $checkpoints = [];
    /** @var list<float> */
    public array $queries = [];

    public function __construct(private readonly string $failure = '')
    {
    }

    public function checkpoint(HttpCheckpoint $checkpoint, float $elapsedMs): void
    {
        $this->failAt('checkpoint');
        $this->checkpoints[] = $checkpoint;
    }

    public function completed(HttpMetrics $metrics): void
    {
        $this->failAt('completed');
        $this->metrics = $metrics;
        ++$this->completedCount;
        $this->scopeClosedAtCompletion = DiagnosticsScopeCleanup::$closed;
    }

    public function response(ServerRequestInterface $request, ResponseInterface $response, ?RouteMatch $match): ResponseInterface
    {
        $this->failAt('response');
        $this->match = $match;
        return $response;
    }

    public function queryCompleted(string $sql, float $durationMs, bool $successful): void
    {
        $this->queries[] = $durationMs;
    }

    public function connectionCompleted(float $durationMs, bool $successful): void
    {
    }

    private function failAt(string $stage): void
    {
        if ($stage === $this->failure) {
            throw new RuntimeException('diagnostics unavailable');
        }
    }
}

final readonly class DiagnosticsMiddleware implements MiddlewareInterface
{
    public function __construct(private ContainerInterface $container, private PdoConnection $connection)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->container->get(DiagnosticsScopeCleanup::class);
        $this->connection->select('SELECT 2');
        $response = $handler->handle($request);
        $this->connection->select('SELECT 3');
        return $response;
    }
}

final class DiagnosticsScopeCleanup
{
    public static bool $closed = false;

    public function __construct(private readonly PdoConnection $connection)
    {
        self::$closed = false;
    }

    public function __destruct()
    {
        usleep(2000);
        $this->connection->select('SELECT 4');
        self::$closed = true;
    }
}

final class DiagnosticsController
{
    public function page(): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'text/plain'], 'unchanged body');
    }

    public function failure(): never
    {
        throw new RuntimeException('application failure');
    }
}

final readonly class DiagnosticsTerminal implements MiddlewareInterface
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->response;
    }
}

final readonly class DiagnosticsInactiveSqlProbe implements MiddlewareInterface
{
    public function __construct(private DatabaseInstrumentation $database)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->database->collect(static fn(DatabaseMeasurements $measurements): ResponseInterface => new Response(202));
    }
}
