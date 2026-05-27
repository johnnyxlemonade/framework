<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Pdo;

use Generator;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Exception\DatabaseException;
use PDO;
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
        ]));

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
        ]));

        $method = new ReflectionMethod(PdoConnection::class, 'resolveOptions');
        $method->setAccessible(true);

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
        ]));

        $method = new ReflectionMethod(PdoConnection::class, 'resolveOptions');
        $method->setAccessible(true);

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
        ]));
    }

    private function prepareUsers(PdoConnection $connection): void
    {
        $connection->statement(
            'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)',
        );
        $connection->statement('INSERT INTO users(name) VALUES (:name)', ['name' => 'Alice']);
    }
}
