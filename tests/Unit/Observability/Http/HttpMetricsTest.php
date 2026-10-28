<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Observability\Http;

use Error;
use InvalidArgumentException;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseMeasurements;
use Lemonade\Framework\Observability\Http\HttpMetrics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class HttpMetricsTest extends TestCase
{
    public function testSnapshotDoesNotChangeWhenMoreSqlOrMemoryIsAllocated(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $startedNs = hrtime(true);
        $metrics = $instrumentation->collect(function (DatabaseMeasurements $sql) use ($instrumentation, $startedNs): HttpMetrics {
            $instrumentation->query('SELECT 1', static fn(): int => 1);
            $metrics = new HttpMetrics(
                durationMs: (hrtime(true) - $startedNs) / 1_000_000,
                phpPeakMemoryBytes: memory_get_peak_usage(false),
                sqlCount: $sql->queryCount(),
                sqlMs: $sql->queryMs(),
            );
            $before = [$metrics->durationMs(), $metrics->phpPeakMemoryBytes(), $metrics->sqlCount(), $metrics->sqlMs()];

            $instrumentation->query('SELECT 2', static fn(): int => 2);
            $allocation = str_repeat('x', 1024 * 1024);

            self::assertSame(2, $sql->queryCount());
            self::assertSame($before, [$metrics->durationMs(), $metrics->phpPeakMemoryBytes(), $metrics->sqlCount(), $metrics->sqlMs()]);
            self::assertSame(1024 * 1024, strlen($allocation));

            return $metrics;
        });

        $instrumentation->collect(static function (DatabaseMeasurements $sql): void {
            self::assertSame(0, $sql->queryCount());
        });

        self::assertSame(1, $metrics->sqlCount());
        self::assertGreaterThanOrEqual(0.0, $metrics->durationMs());
    }

    public function testMeasurementsCannotBeOverwritten(): void
    {
        $metrics = new HttpMetrics(1.0, 1024, 0, 0.0);

        self::assertSame([], get_object_vars($metrics));

        $this->expectException(Error::class);
        (new ReflectionProperty(HttpMetrics::class, 'durationMs'))->setValue($metrics, 2.0);
    }

    #[DataProvider('invalidMeasurements')]
    public function testInvalidMeasurementsAreRejected(float $durationMs, int $peak, int $count, float $sqlMs): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HttpMetrics($durationMs, $peak, $count, $sqlMs);
    }

    /**
     * @return iterable<string, array{float, int, int, float}>
     */
    public static function invalidMeasurements(): iterable
    {
        yield 'negative duration' => [-1.0, 0, 0, 0.0];
        yield 'negative memory' => [0.0, -1, 0, 0.0];
        yield 'negative count' => [0.0, 0, -1, 0.0];
        yield 'negative SQL duration' => [0.0, 0, 0, -1.0];
        yield 'infinite duration' => [INF, 0, 0, 0.0];
        yield 'NaN duration' => [NAN, 0, 0, 0.0];
        yield 'infinite SQL duration' => [0.0, 0, 0, INF];
        yield 'NaN SQL duration' => [0.0, 0, 0, NAN];
    }
}
