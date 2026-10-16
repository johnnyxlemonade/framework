<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability;

use Lemonade\Framework\Container\ContainerBuilder;
use Lemonade\Framework\Container\ServiceLifetime;
use Lemonade\Framework\Observability\Benchmark\Benchmark;
use Lemonade\Framework\Observability\Benchmark\BenchmarkResponseInjector;
use Lemonade\Framework\Observability\Benchmark\BenchmarkServiceProvider;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfig;
use Lemonade\Framework\Observability\Benchmark\Config\BenchmarkConfigResolver;
use PHPUnit\Framework\TestCase;

final class BenchmarkTest extends TestCase
{
    public function testStartAlwaysReplacesTheCurrentRun(): void
    {
        $benchmark = new Benchmark();

        $first = $benchmark->start(['request' => 'first']);
        $second = $benchmark->start(['request' => 'second']);

        self::assertNotSame($first, $second);
        self::assertTrue($first->isStopped());
        self::assertSame($second, $benchmark->current());
    }

    public function testCurrentOrStartNeverReusesAStoppedRun(): void
    {
        $benchmark = new Benchmark();
        $first = $benchmark->currentOrStart();
        $first->stop();

        $second = $benchmark->currentOrStart();

        self::assertNotSame($first, $second);
        self::assertFalse($second->isStopped());
        self::assertSame($second, $benchmark->current());
    }

    public function testEndStopsAndDetachesTheCurrentRun(): void
    {
        $benchmark = new Benchmark();
        $run = $benchmark->start();

        self::assertSame($run, $benchmark->end());
        self::assertTrue($run->isStopped());
        self::assertNull($benchmark->current());
    }

    public function testDatabaseMeasurementsDoNotCarryIntoTheNextRun(): void
    {
        $benchmark = new Benchmark();
        $first = $benchmark->start();
        $benchmark->recordDatabaseConnection(1.25);
        $benchmark->recordDatabaseQuery('SELECT first', ['first'], 0.5, true);
        $benchmark->end();

        $second = $benchmark->start();
        $benchmark->recordDatabaseQuery('SELECT second', [], 0.25, true);

        self::assertSame(1, $first->database()['query_count']);
        self::assertSame([[
            'sql' => 'SELECT first',
            'bindings' => ['first'],
            'elapsed_ms' => 0.5,
        ]], $first->database()['queries']);
        self::assertSame([
            'connection_ms' => 0.0,
            'query_ms' => 0.25,
            'query_count' => 1,
            'queries' => [[
                'sql' => 'SELECT second',
                'bindings' => [],
                'elapsed_ms' => 0.25,
            ]],
        ], $second->database());
    }

    public function testProviderKeepsOnlyStatefulBenchmarkServicesAsSingletons(): void
    {
        $builder = new ContainerBuilder();
        (new BenchmarkServiceProvider())->register($builder);
        $plan = $builder->compile();

        self::assertSame(ServiceLifetime::Singleton, $plan->definition(Benchmark::class)?->lifetime);
        self::assertSame(ServiceLifetime::Singleton, $plan->definition(BenchmarkConfig::class)?->lifetime);
        self::assertFalse($plan->hasDefinition(BenchmarkConfigResolver::class));
        self::assertFalse($plan->hasDefinition(BenchmarkResponseInjector::class));
    }
}
