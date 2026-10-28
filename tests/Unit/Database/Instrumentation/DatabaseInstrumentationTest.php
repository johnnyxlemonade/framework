<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Instrumentation;

use Lemonade\Framework\Database\Instrumentation\DatabaseObserverInterface;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseMeasurements;
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use WeakReference;

final class DatabaseInstrumentationTest extends TestCase
{
    public function testInactiveOperationsPreserveResultsAndFailuresWithoutAffectingNextCapture(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $result = new stdClass();
        self::assertSame($result, $instrumentation->query('SELECT 1', static fn(): object => $result));
        self::assertSame($result, $instrumentation->connection(static fn(): object => $result));
        $failure = new RuntimeException('operation failed');
        $caught = null;

        try {
            $instrumentation->query('invalid', static function () use ($failure): void {
                throw $failure;
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }
        self::assertSame($failure, $caught);

        $instrumentation->collect(static function (DatabaseMeasurements $measurements): void {
            self::assertSame(0, $measurements->queryCount());
            self::assertSame(0.0, $measurements->queryMs());
        });
    }

    public function testQueryTotalsUseExactlyTheSamplesDeliveredToObserver(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $observer = new RecordingObserver();
        $result = new stdClass();
        $failure = new RuntimeException('query failed');

        $returned = $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($instrumentation, $observer, $result, $failure): object {
            self::assertSame($result, $instrumentation->connection(static fn(): object => $result));
            self::assertSame(0, $measurements->queryCount());
            self::assertSame(0.0, $measurements->queryMs());

            self::assertSame($result, $instrumentation->query('SELECT 1', static fn(): object => $result));
            $caught = null;
            try {
                $instrumentation->query('invalid SQL', static function () use ($failure): void {
                    throw $failure;
                });
            } catch (RuntimeException $exception) {
                $caught = $exception;
            }
            self::assertSame($failure, $caught);

            self::assertSame(2, $measurements->queryCount());
            self::assertSame(array_sum(array_column($observer->queries, 'duration')), $measurements->queryMs());
            self::assertSame([true, false], array_column($observer->queries, 'successful'));
            self::assertSame(['SELECT 1', 'invalid SQL'], array_column($observer->queries, 'sql'));
            self::assertCount(1, $observer->connections);
            self::assertTrue($observer->connections[0]['successful']);
            self::assertGreaterThanOrEqual(0.0, $measurements->queryMs());

            return $result;
        }, $observer);

        self::assertSame($result, $returned);
    }

    public function testObserverFailureKeepsSuccessfulResultAndRemainingAggregates(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $observer = $this->createMock(DatabaseObserverInterface::class);
        $observer->expects(self::once())->method('queryCompleted')->willThrowException(new RuntimeException('observer failed'));
        $observer->expects(self::never())->method('connectionCompleted');

        $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($instrumentation): void {
            self::assertSame(42, $instrumentation->query('SELECT 42', static fn(): int => 42));
            self::assertSame(43, $instrumentation->query('SELECT 43', static fn(): int => 43));
            self::assertSame('connected', $instrumentation->connection(static fn(): string => 'connected'));
            self::assertSame(2, $measurements->queryCount());
        }, $observer);
    }

    public function testObserverFailureCannotReplaceOriginalQueryException(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $observer = $this->createMock(DatabaseObserverInterface::class);
        $observer->method('queryCompleted')->willThrowException(new RuntimeException('observer failed'));
        $failure = new RuntimeException('database failed');
        $caught = null;

        try {
            $instrumentation->collect(static function (DatabaseMeasurements $measurements) use ($instrumentation, $failure): void {
                try {
                    $instrumentation->query('invalid', static function () use ($failure): void {
                        throw $failure;
                    });
                } finally {
                    self::assertSame(1, $measurements->queryCount());
                }
            }, $observer);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }
        self::assertSame($failure, $caught);

        $instrumentation->collect(static function (DatabaseMeasurements $measurements): void {
            self::assertSame(0, $measurements->queryCount());
        });
    }

    public function testConnectionAndObserverFailuresDoNotCreateAQueryOrReplaceTheException(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $observer = $this->createMock(DatabaseObserverInterface::class);
        $observer->expects(self::once())->method('connectionCompleted')
            ->with(self::greaterThanOrEqual(0.0), false)
            ->willThrowException(new RuntimeException('observer failed'));
        $observer->expects(self::never())->method('queryCompleted');
        $failure = new RuntimeException('connection failed');

        $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($instrumentation, $failure): void {
            $caught = null;
            try {
                $instrumentation->connection(static function () use ($failure): void {
                    throw $failure;
                });
            } catch (RuntimeException $exception) {
                $caught = $exception;
            }
            self::assertSame($failure, $caught);

            self::assertSame(0, $measurements->queryCount());
            self::assertSame(0.0, $measurements->queryMs());
            self::assertSame(1, $instrumentation->query('SELECT 1', static fn(): int => 1));
            self::assertSame(1, $measurements->queryCount());
        }, $observer);
    }

    public function testSequentialCapturesOnTheSameInstanceReleaseObserversAndTotals(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $observer = new RecordingObserver();
        $reference = WeakReference::create($observer);
        $first = $instrumentation->collect(static function (DatabaseMeasurements $measurements) use ($instrumentation): DatabaseMeasurements {
            $instrumentation->query('first', static fn(): null => null);

            return $measurements;
        }, $observer);
        unset($observer);
        self::assertNull($reference->get());

        $instrumentation->query('outside', static fn(): null => null);
        $nextObserver = new RecordingObserver();
        $instrumentation->collect(static function (DatabaseMeasurements $measurements) use ($instrumentation, $first): void {
            self::assertNotSame($first, $measurements);
            self::assertSame(0, $measurements->queryCount());
            $instrumentation->query('second', static fn(): null => null);
            self::assertSame(1, $measurements->queryCount());
        }, $nextObserver);

        self::assertSame(1, $first->queryCount());
        self::assertSame(['second'], array_column($nextObserver->queries, 'sql'));
    }

    public function testOverlappingCaptureIsRejectedWithoutReplacingTheActiveCapture(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $observer = new RecordingObserver();

        $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($instrumentation): void {
            try {
                $instrumentation->collect(static function (): void {
                    throw new RuntimeException('must not run');
                });
            } catch (LogicException) {
                $instrumentation->query('outer', static fn(): null => null);
            }

            self::assertSame(1, $measurements->queryCount());
        }, $observer);

        self::assertSame(['outer'], array_column($observer->queries, 'sql'));
    }

    public function testConsumerFailureReleasesANonFailingObserverAndAllowsTheNextCapture(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $observer = new RecordingObserver();
        $reference = WeakReference::create($observer);
        $failure = new RuntimeException('request failed');
        $caught = null;

        try {
            $instrumentation->collect(static function () use ($instrumentation, $failure): void {
                $instrumentation->query('first', static fn(): null => null);
                throw $failure;
            }, $observer);
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertSame($failure, $caught);
        unset($observer);
        self::assertNull($reference->get());
        $instrumentation->collect(static function (DatabaseMeasurements $measurements) use ($instrumentation): void {
            self::assertSame(0, $measurements->queryCount());
            $instrumentation->query('next', static fn(): null => null);
            self::assertSame(1, $measurements->queryCount());
        });
    }

    public function testResultAndCallbackCapturesAreNotRetained(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $result = new stdClass();
        $reference = WeakReference::create($result);

        $instrumentation->collect(static function () use ($instrumentation, $result): void {
            $instrumentation->query('SELECT 1', static fn(): object => $result);
        });
        unset($result);

        self::assertNull($reference->get());
    }

    public function testNativePdoPrepareExecuteAndFetchBoundariesWithoutUsingLegacyDrivers(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('PDO SQLite is required for the isolated execution-boundary test.');
        }

        $instrumentation = new DatabaseInstrumentation();
        $observer = new RecordingObserver();
        $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($instrumentation): void {
            $pdo = $instrumentation->connection(static fn(): PDO => new PDO('sqlite::memory:'));
            $statement = $instrumentation->query('SELECT :value', static function () use ($pdo): \PDOStatement {
                $statement = $pdo->prepare('SELECT :value');
                self::assertNotFalse($statement);
                $statement->bindValue(':value', 'private binding');
                $statement->execute();

                return $statement;
            });
            $beforeFetch = $measurements->queryMs();
            self::assertSame('private binding', $statement->fetchColumn());
            self::assertSame($beforeFetch, $measurements->queryMs());

            try {
                $instrumentation->query('invalid SQL', static fn(): mixed => $pdo->query('invalid SQL'));
                self::fail('Expected failed SQL.');
            } catch (PDOException) {
                self::assertSame(2, $measurements->queryCount());
            }
        }, $observer);

        self::assertSame(['SELECT :value', 'invalid SQL'], array_column($observer->queries, 'sql'));
        self::assertSame([true, false], array_column($observer->queries, 'successful'));
    }
}

final class RecordingObserver implements DatabaseObserverInterface
{
    /**
     * @var list<array{sql: string, duration: float, successful: bool}>
     */
    public array $queries = [];

    /**
     * @var list<array{duration: float, successful: bool}>
     */
    public array $connections = [];

    public function queryCompleted(string $sql, float $durationMs, bool $successful): void
    {
        $this->queries[] = ['sql' => $sql, 'duration' => $durationMs, 'successful' => $successful];
    }

    public function connectionCompleted(float $durationMs, bool $successful): void
    {
        $this->connections[] = ['duration' => $durationMs, 'successful' => $successful];
    }
}
