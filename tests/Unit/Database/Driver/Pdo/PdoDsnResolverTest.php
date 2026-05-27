<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Pdo;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Pdo\PdoDsnResolver;
use Lemonade\Framework\Database\Exception\DatabaseException;
use PHPUnit\Framework\TestCase;

final class PdoDsnResolverTest extends TestCase
{
    public function testResolveReturnsExplicitDsn(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
        ]);

        self::assertSame('sqlite::memory:', PdoDsnResolver::resolve($config));
    }

    public function testIsMysqlReturnsTrueForExplicitMysqlDsn(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
        ]);

        self::assertTrue(PdoDsnResolver::isMysql($config));
        self::assertFalse(PdoDsnResolver::isSqlite($config));
    }

    public function testIsMysqlReturnsFalseForSqliteDsn(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
        ]);

        self::assertFalse(PdoDsnResolver::isMysql($config));
        self::assertTrue(PdoDsnResolver::isSqlite($config));
    }

    public function testResolveBuildsMysqlFallbackDsn(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'app',
            'charset' => 'utf8mb4',
        ]);

        self::assertSame(
            'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
            PdoDsnResolver::resolve($config),
        );
        self::assertTrue(PdoDsnResolver::isMysql($config));
        self::assertFalse(PdoDsnResolver::isSqlite($config));
    }

    public function testIsSqliteReturnsTrueForSqliteFilePathDsn(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite:/tmp/app.sqlite',
        ]);

        self::assertTrue(PdoDsnResolver::isSqlite($config));
        self::assertFalse(PdoDsnResolver::isMysql($config));
    }

    public function testResolveThrowsWhenDsnAndDatabaseAreMissing(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => '',
            'database' => '',
        ]);

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('PDO requires "dsn" or non-empty "database"');

        PdoDsnResolver::resolve($config);
    }
}
