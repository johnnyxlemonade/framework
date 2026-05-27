<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Pdo;

use Lemonade\Framework\Container\Container;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\DatabaseDriverRegistry;
use Lemonade\Framework\Database\Driver\Mysql\MysqlDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteDatabaseServiceProvider;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteSchemaGrammar;
use Lemonade\Framework\Database\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

final class PdoDatabaseServiceProviderTest extends TestCase
{
    public function testPdoWithoutDialectDoesNotProvideSchemaGrammar(): void
    {
        [$container, $registry] = $this->bootProviders();

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('requires explicit supported dialect');

        $registry->resolveSchemaGrammar(
            Driver::Pdo,
            DatabaseConfig::fromArray([
                'driver' => 'pdo',
                'dsn' => 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',
            ]),
            $container,
        );
    }

    public function testPdoMysqlDialectWithMysqlDsnProvidesMysqlSchemaGrammar(): void
    {
        [$container, $registry] = $this->bootProviders();

        $grammar = $registry->resolveSchemaGrammar(
            Driver::Pdo,
            DatabaseConfig::fromArray([
                'driver' => 'pdo',
                'dialect' => 'mysql',
                'dsn' => 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',
            ]),
            $container,
        );

        self::assertInstanceOf(MysqlSchemaGrammar::class, $grammar);
    }

    public function testPdoMysqlDialectWithFallbackMysqlDsnProvidesMysqlSchemaGrammar(): void
    {
        [$container, $registry] = $this->bootProviders();

        $grammar = $registry->resolveSchemaGrammar(
            Driver::Pdo,
            DatabaseConfig::fromArray([
                'driver' => 'pdo',
                'dialect' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'app',
                'charset' => 'utf8mb4',
            ]),
            $container,
        );

        self::assertInstanceOf(MysqlSchemaGrammar::class, $grammar);
    }

    public function testPdoMysqlDialectWithNonMysqlDsnThrowsInvalidConfiguration(): void
    {
        [$container, $registry] = $this->bootProviders();

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('requires DSN with "mysql:" prefix');

        $registry->resolveSchemaGrammar(
            Driver::Pdo,
            DatabaseConfig::fromArray([
                'driver' => 'pdo',
                'dialect' => 'mysql',
                'dsn' => 'sqlite::memory:',
            ]),
            $container,
        );
    }

    public function testPdoSqliteDialectWithSqliteDsnProvidesSqliteSchemaGrammar(): void
    {
        [$container, $registry] = $this->bootProviders();

        $grammar = $registry->resolveSchemaGrammar(
            Driver::Pdo,
            DatabaseConfig::fromArray([
                'driver' => 'pdo',
                'dialect' => 'sqlite',
                'dsn' => 'sqlite::memory:',
            ]),
            $container,
        );

        self::assertInstanceOf(SqliteSchemaGrammar::class, $grammar);
    }

    public function testPdoSqliteDialectWithNonSqliteDsnThrowsInvalidConfiguration(): void
    {
        [$container, $registry] = $this->bootProviders();

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('PDO dialect "sqlite" requires DSN with "sqlite:" prefix.');

        $registry->resolveSchemaGrammar(
            Driver::Pdo,
            DatabaseConfig::fromArray([
                'driver' => 'pdo',
                'dialect' => 'sqlite',
                'dsn' => 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',
            ]),
            $container,
        );
    }

    public function testPdoDriverWiringUsesMysqlStyleIdentifierProtectionForMysqlDialect(): void
    {
        [$container, $registry] = $this->bootProviders();

        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'mysql',
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
        ]);

        $driver = $registry->resolveDriver(
            Driver::Pdo,
            new PdoConnection($config),
            $config,
            $container,
        );

        self::assertInstanceOf(PdoDatabaseDriver::class, $driver);
        self::assertSame('`users`.`id`', $driver->protect_identifiers('users.id'));
    }

    public function testPdoDriverWiringUsesSqliteStyleIdentifierProtectionForSqliteDialect(): void
    {
        [$container, $registry] = $this->bootProviders();

        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);

        $driver = $registry->resolveDriver(
            Driver::Pdo,
            new PdoConnection($config),
            $config,
            $container,
        );

        self::assertInstanceOf(PdoDatabaseDriver::class, $driver);
        self::assertSame('"users"."id"', $driver->protect_identifiers('users.id'));
    }

    /**
     * @return array{0:Container,1:DatabaseDriverRegistry}
     */
    private function bootProviders(): array
    {
        $container = new Container();
        $container->singleton(DatabaseDriverRegistry::class, DatabaseDriverRegistry::class);
        $container->singleton(DatabaseConfig::class, static fn(): DatabaseConfig => DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'mysql',
            'dsn' => 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',
        ]));

        (new MysqlDatabaseServiceProvider())->register($container);
        (new PdoDatabaseServiceProvider())->register($container);
        (new SqliteDatabaseServiceProvider())->register($container);

        /** @var DatabaseDriverRegistry $registry */
        $registry = $container->get(DatabaseDriverRegistry::class);

        return [$container, $registry];
    }
}
