<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Integration\Database;

use Lemonade\Framework\Database\Connection\ConnectionFactory;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Instrumentation\DatabaseObserverInterface;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseMeasurements;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DriverInstrumentationTest extends TestCase
{
    #[DataProvider('drivers')]
    public function testDirectApisCountOnceAndSeparateConnectionSetup(string $driver): void
    {
        if ($driver === 'odbc' && (!extension_loaded('odbc') || getenv('TEST_ODBC_DSN') === false)) {
            self::markTestSkipped('ODBC requires ext-odbc and TEST_ODBC_DSN.');
        }
        $instrumentation = new DatabaseInstrumentation();
        $config = DatabaseConfig::fromArray([
            'driver' => $driver,
            'dialect' => 'mysql',
            'host' => self::environment('DB_HOST', 'db'),
            'port' => (int) (self::environment('DB_PORT', '3306')),
            'database' => self::environment('DB_NAME', 'db'),
            'username' => self::environment('DB_USER', 'db'),
            'password' => self::environment('DB_PASS', 'db'),
            'dsn' => $driver === 'odbc'
                ? getenv('TEST_ODBC_DSN')
                : sprintf('mysql:host=%s;port=%s;dbname=%s', self::environment('DB_HOST', 'db'), self::environment('DB_PORT', '3306'), self::environment('DB_NAME', 'db')),
        ]);
        $connection = (new ConnectionFactory($instrumentation))->create($config);
        $observer = $this->createMock(DatabaseObserverInterface::class);
        $observer->expects(self::once())->method('connectionCompleted')->with(self::greaterThan(0.0), true);
        $observer->expects(self::exactly(4))->method('queryCompleted');
        $totals = $instrumentation->collect(static function (DatabaseMeasurements $measurements) use ($connection): DatabaseMeasurements {
            self::assertCount(1, $connection->select('SELECT 1 AS value'));
            $connection->statement('SELECT ? AS value', [2]);
            self::assertCount(1, iterator_to_array($connection->cursor('SELECT ? AS value', [3])));
            try {
                $connection->select('SELECT * FROM lemonade_diagnostics_nonexistent_table');
                self::fail('Invalid SQL must throw.');
            } catch (DatabaseException) {
                self::assertSame(4, $measurements->queryCount());
            }
            return $measurements;
        }, $observer);
        self::assertSame(4, $totals->queryCount());
        self::assertGreaterThan(0.0, $totals->queryMs());
        $instrumentation->collect(static function (DatabaseMeasurements $next) use ($connection): void {
            $connection->select('SELECT 4');
            self::assertSame(1, $next->queryCount());
        });
        self::assertSame(4, $totals->queryCount());
    }

    private static function environment(string $name, string $fallback): string
    {
        $value = getenv($name);
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    /** @return iterable<string, array{string}> */
    public static function drivers(): iterable
    {
        yield 'PDO MySQL' => ['pdo'];
        yield 'MySQLi' => ['mysql'];
        yield 'ODBC' => ['odbc'];
    }
}
