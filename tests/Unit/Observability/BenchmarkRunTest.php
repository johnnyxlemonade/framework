<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability;

use Lemonade\Framework\Observability\Benchmark\BenchmarkRun;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BenchmarkRunTest extends TestCase
{
    public function testDatabaseSummaryAlwaysTracksQueriesButOnlyKeepsDetailsWhenEnabled(): void
    {
        $run = new BenchmarkRun();
        $run->recordDatabaseConnection(1.25);
        $run->recordDatabaseQuery('SELECT * FROM users WHERE email = ?', ['user@example.test'], 0.5, false);
        $run->recordDatabaseQuery('SELECT 1', [], 0.25, true);

        self::assertSame([
            'connection_ms' => 1.25,
            'query_ms' => 0.75,
            'query_count' => 2,
            'queries' => [[
                'sql' => 'SELECT 1',
                'bindings' => [],
                'elapsed_ms' => 0.25,
            ]],
        ], $run->database());
    }

    /**
     * @param callable(BenchmarkRun):void $mutation
     */
    #[DataProvider('stoppedRunMutationProvider')]
    public function testStoppedRunRejectsMutation(callable $mutation): void
    {
        $run = new BenchmarkRun();
        $run->stop();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A stopped benchmark run cannot be mutated.');

        $mutation($run);
    }

    /**
     * @return iterable<string, array{callable(BenchmarkRun):void}>
     */
    public static function stoppedRunMutationProvider(): iterable
    {
        yield 'mark' => [static function (BenchmarkRun $run): void {
            $run->mark('late');
        }];
        yield 'context' => [static function (BenchmarkRun $run): void {
            $run->with('late', true);
        }];
        yield 'database query' => [
            static function (BenchmarkRun $run): void {
                $run->recordDatabaseQuery('SELECT 1', [], 1.0, true);
            },
        ];
        yield 'database connection' => [
            static function (BenchmarkRun $run): void {
                $run->recordDatabaseConnection(1.0);
            },
        ];
    }
}
