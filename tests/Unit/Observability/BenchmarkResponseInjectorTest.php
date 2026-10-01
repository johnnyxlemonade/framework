<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Observability\Benchmark\BenchmarkResponseInjector;
use Lemonade\Framework\Observability\Benchmark\BenchmarkRun;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class BenchmarkResponseInjectorTest extends TestCase
{
    public function testDevelopmentInjectAddsHeadersAndHtmlCommentWhenEnabled(): void
    {
        $injector = new BenchmarkResponseInjector(
            new BenchmarkConfig(true),
            $this->context(Environment::Development),
        );
        $run = new BenchmarkRun();

        $response = $injector->inject(
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
            $run,
        );

        self::assertStringContainsString('benchmark:', (string) $response->getBody());
        self::assertTrue($response->hasHeader('X-Benchmark-Time-Ms'));
        self::assertSame('0.000', $response->getHeaderLine('X-Benchmark-Db-Time-Ms'));
        self::assertSame('0', $response->getHeaderLine('X-Benchmark-Db-Query-Count'));
    }

    public function testDevelopmentInjectSkipsHtmlCommentWhenDisabled(): void
    {
        $injector = new BenchmarkResponseInjector(
            new BenchmarkConfig(false),
            $this->context(Environment::Development),
        );
        $run = new BenchmarkRun();

        $response = $injector->inject(
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
            $run,
        );

        self::assertSame('<html></html>', (string) $response->getBody());
    }

    public function testDevelopmentInjectAddsDatabaseSummaryHeaders(): void
    {
        $injector = new BenchmarkResponseInjector(
            new BenchmarkConfig(false),
            $this->context(Environment::Development),
        );
        $run = new BenchmarkRun();
        $run->recordDatabaseConnection(1.25);
        $run->recordDatabaseQuery('SELECT 1', [], 0.5, false);

        $response = $injector->inject(new Response(), $run);

        self::assertSame('1.250', $response->getHeaderLine('X-Benchmark-Db-Connection-Ms'));
        self::assertSame('0.500', $response->getHeaderLine('X-Benchmark-Db-Time-Ms'));
        self::assertSame('1', $response->getHeaderLine('X-Benchmark-Db-Query-Count'));
    }

    public function testProductionInjectDoesNotExposeBenchmarkDiagnostics(): void
    {
        $injector = new BenchmarkResponseInjector(
            new BenchmarkConfig(true),
            $this->context(Environment::Production),
        );
        $response = $injector->inject(
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
            new BenchmarkRun(),
        );

        self::assertFalse($response->hasHeader('X-Benchmark-Time-Ms'));
        self::assertStringNotContainsString('benchmark:', (string) $response->getBody());
    }

    private function context(Environment $environment): ApplicationContext
    {
        return new ApplicationContext(
            $environment,
            new Path(__DIR__),
            DebugMode::disabled(),
        );
    }
}
