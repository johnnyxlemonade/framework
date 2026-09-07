<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability;

use Lemonade\Framework\Observability\Benchmark\BenchmarkRun;
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
}
