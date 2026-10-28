<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Pdo;

use Generator;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Instrumentation\DatabaseObserverInterface;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseMeasurements;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

final class PdoConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension is required for PDO connection tests.');
        }
    }

    public function testInvalidConfigWithoutDsnAndDatabaseThrows(): void
    {
        $connection = new PdoConnection(DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'database' => '',
            'dsn' => '',
        ]), new DatabaseInstrumentation());

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('PDO requires "dsn" or non-empty "database"');

        $connection->select('SELECT 1');
    }

    public function testSelectWithNamedBindings(): void
    {
        $connection = $this->sqliteConnection();
        $this->prepareUsers($connection);

        $rows = $connection->select(
            'SELECT id, name FROM users WHERE name = :name',
            ['name' => 'Alice'],
        );

        self::assertSame([['id' => 1, 'name' => 'Alice']], $rows);
    }

    public function testStatementWithPositionalBindingsAndAffectedRows(): void
    {
        $connection = $this->sqliteConnection();
        $this->prepareUsers($connection);

        $affected = $connection->statement(
            'UPDATE users SET name = ? WHERE id = ?',
            ['Bob', 1],
        );

        self::assertSame(1, $affected);
        self::assertSame(1, $connection->affectedRows());
        self::assertSame([['name' => 'Bob']], $connection->select('SELECT name FROM users WHERE id = 1'));
    }

    public function testTransactionCommitPersistsChanges(): void
    {
        $connection = $this->sqliteConnection();
        $this->prepareUsers($connection);

        $connection->transaction(static function (ConnectionInterface $conn): void {
            $conn->statement('INSERT INTO users(name) VALUES (?)', ['Committed']);
        });

        self::assertSame(2, count($connection->select('SELECT id FROM users')));
    }

    public function testTransactionRollbackOnException(): void
    {
        $connection = $this->sqliteConnection();
        $this->prepareUsers($connection);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('force rollback');

        try {
            $connection->transaction(static function (ConnectionInterface $conn): void {
                $conn->statement('INSERT INTO users(name) VALUES (?)', ['RolledBack']);
                throw new RuntimeException('force rollback');
            });
        } finally {
            self::assertSame(1, count($connection->select('SELECT id FROM users')));
            self::assertFalse($connection->inTransaction());
        }
    }

    public function testNestedTransactionReusesTheOuterTransactionAndRollsBackWithIt(): void
    {
        $connection = $this->sqliteConnection();
        $this->prepareUsers($connection);

        try {
            $connection->transaction(static function (ConnectionInterface $outer): void {
                $outer->statement('INSERT INTO users(name) VALUES (?)', ['Outer']);
                $outer->transaction(static function (ConnectionInterface $inner): void {
                    $inner->statement('INSERT INTO users(name) VALUES (?)', ['Inner']);
                });
                throw new RuntimeException('outer rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('outer rollback', $exception->getMessage());
        }

        self::assertSame([['id' => 1]], $connection->select('SELECT id FROM users ORDER BY id'));
        self::assertFalse($connection->inTransaction());
    }

    public function testCursorStreamsRowsAsGenerator(): void
    {
        $connection = $this->sqliteConnection();
        $this->prepareUsers($connection);
        $connection->statement('INSERT INTO users(name) VALUES (?)', ['Bob']);
        $connection->statement('INSERT INTO users(name) VALUES (?)', ['Charlie']);

        $cursor = $connection->cursor('SELECT id, name FROM users ORDER BY id');

        self::assertInstanceOf(Generator::class, $cursor);

        $rows = [];
        foreach ($cursor as $row) {
            $rows[] = $row;
        }

        self::assertSame(
            [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
                ['id' => 3, 'name' => 'Charlie'],
            ],
            $rows,
        );
    }

    public function testInstrumentationCapturesDirectSelectStatementAndCursorExactlyOnce(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $connection = new PdoConnection(DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
        ]), $instrumentation);
        $this->prepareUsers($connection);
        $observer = $this->createMock(DatabaseObserverInterface::class);
        $observer->expects(self::exactly(3))->method('queryCompleted')->with(
            self::isType('string'),
            self::greaterThanOrEqual(0.0),
            true,
        );
        $observer->expects(self::never())->method('connectionCompleted');
        $first = $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($connection): DatabaseMeasurements {
            $connection->select('SELECT id FROM users WHERE name = ?', ['Alice']);
            $connection->statement('UPDATE users SET name = ? WHERE id = ?', ['Alice', 1]);
            iterator_to_array($connection->cursor('SELECT id FROM users'));
            return $measurements;
        }, $observer);
        self::assertSame(3, $first->queryCount());
        self::assertGreaterThan(0.0, $first->queryMs());
        $second = $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($connection): DatabaseMeasurements {
            $connection->select('SELECT 1');
            return $measurements;
        });
        self::assertSame(1, $second->queryCount());
        self::assertSame(3, $first->queryCount());
        $connection->select('SELECT 2');
        self::assertSame(1, $second->queryCount());
    }

    public function testLazyConnectionIsMeasuredSeparatelyFromSql(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $connection = new PdoConnection(DatabaseConfig::fromArray([
            'driver' => 'pdo', 'dsn' => 'sqlite::memory:',
        ]), $instrumentation);
        $observer = $this->createMock(DatabaseObserverInterface::class);
        $observer->expects(self::once())->method('connectionCompleted')->with(self::greaterThan(0.0), true);
        $observer->expects(self::once())->method('queryCompleted')->with('SELECT 1', self::greaterThan(0.0), true);
        $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($connection): void {
            self::assertSame(0, $measurements->queryCount());
            $connection->select('SELECT 1');
            self::assertSame(1, $measurements->queryCount());
        }, $observer);
    }

    public function testObserverFailureDoesNotReplaceQueryResultsOrDatabaseExceptions(): void
    {
        $instrumentation = new DatabaseInstrumentation();
        $connection = new PdoConnection(DatabaseConfig::fromArray([
            'driver' => 'pdo', 'dsn' => 'sqlite::memory:',
        ]), $instrumentation);
        $observer = $this->createMock(DatabaseObserverInterface::class);
        $observer->expects(self::once())->method('queryCompleted')->willThrowException(new RuntimeException('Observer failure'));
        $instrumentation->collect(function (DatabaseMeasurements $measurements) use ($connection): void {
            self::assertSame([['value' => 'secret']], $connection->select(
                'SELECT :value AS value',
                ['value' => 'secret'],
            ));
            try {
                $connection->select('SELECT * FROM missing_observer_test_table');
                self::fail('The failed SQL statement must still throw.');
            } catch (DatabaseException $exception) {
                self::assertInstanceOf(PDOException::class, $exception->getPrevious());
                self::assertStringContainsString('missing_observer_test_table', $exception->getMessage());
            }
            self::assertSame(2, $measurements->queryCount());
        }, $observer);
    }

    public function testPdoOptionsAreNormalizedAndMergedWithDefaults(): void
    {
        $connection = new PdoConnection(DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
            'persistent' => true,
            'options' => [
                (string) PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_PERSISTENT => false,
            ],
        ]), new DatabaseInstrumentation());

        $method = new ReflectionMethod(PdoConnection::class, 'resolveOptions');

        /** @var array<int, mixed> $options */
        $options = $method->invoke($connection);

        self::assertSame(5, $options[PDO::ATTR_TIMEOUT]);
        self::assertTrue($options[PDO::ATTR_EMULATE_PREPARES]);
        self::assertTrue($options[PDO::ATTR_PERSISTENT]);
        self::assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        self::assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
    }

    public function testPdoOptionsIgnoreInvalidStringKeysAndDoNotSetPersistentWhenDisabled(): void
    {
        $connection = new PdoConnection(DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
            'persistent' => false,
            'options' => [
                'foo' => 'bar',
                (string) PDO::ATTR_TIMEOUT => 3,
            ],
        ]), new DatabaseInstrumentation());

        $method = new ReflectionMethod(PdoConnection::class, 'resolveOptions');

        /** @var array<int, mixed> $options */
        $options = $method->invoke($connection);

        self::assertSame(3, $options[PDO::ATTR_TIMEOUT]);
        self::assertArrayNotHasKey(PDO::ATTR_PERSISTENT, $options);
    }

    private function sqliteConnection(): PdoConnection
    {
        return new PdoConnection(DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
            'options' => [],
        ]), new DatabaseInstrumentation());
    }

    private function prepareUsers(PdoConnection $connection): void
    {
        $connection->statement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
        );
        $connection->statement('INSERT INTO users(name) VALUES (:name)', ['name' => 'Alice']);
    }
}
