<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Connection;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\DatabaseDialect;
use Lemonade\Framework\Database\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigDialectTest extends TestCase
{
    public function testConfigWithoutDriverDefaultsToMysqlDriver(): void
    {
        $config = DatabaseConfig::fromArray([]);

        self::assertSame('mysql', $config->driver()->value);
    }

    public function testMysqlDriverHasImplicitMysqlDialect(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'mysql',
        ]);

        self::assertSame(DatabaseDialect::Mysql, $config->dialect());
    }

    public function testOdbcDriverHasImplicitOdbcDialect(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'odbc',
        ]);

        self::assertSame(DatabaseDialect::Odbc, $config->dialect());
    }

    public function testPdoDriverWithoutExplicitDialectHasNoDialect(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
        ]);

        self::assertNull($config->dialect());
    }

    public function testPdoDriverWithExplicitMysqlDialectReturnsMysqlDialect(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'mysql',
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
        ]);

        self::assertSame(DatabaseDialect::Mysql, $config->dialect());
    }

    public function testInvalidDialectThrowsInvalidConfigurationException(): void
    {
        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Unsupported database dialect');

        DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
        ]);
    }
}
