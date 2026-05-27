<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Pdo;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Mysql\MysqlIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteIdentifierEscaper;
use Lemonade\Framework\Database\Sql\IdentifierProtector;
use PHPUnit\Framework\TestCase;

final class PdoDatabaseDriverTest extends TestCase
{
    public function testEscapeIdentifiersUsesMysqlCompatibleBackticks(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'mysql',
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
        ]);

        $driver = new PdoDatabaseDriver(
            connection: new PdoConnection($config),
            identifierEscaper: new MysqlIdentifierEscaper($config->prefix()),
            identifierProtector: new IdentifierProtector(new MysqlIdentifierEscaper($config->prefix())),
        );

        self::assertSame('`table`', $driver->escape_identifiers('table'));
        self::assertSame('`we``ird`', $driver->escape_identifiers('we`ird'));
        self::assertSame('*', $driver->escape_identifiers('*'));
    }

    public function testProtectIdentifiersUsesMysqlCompatibleEscaping(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'mysql',
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=app;charset=utf8mb4',
            'prefix' => '',
        ]);

        $driver = new PdoDatabaseDriver(
            connection: new PdoConnection($config),
            identifierEscaper: new MysqlIdentifierEscaper($config->prefix()),
            identifierProtector: new IdentifierProtector(new MysqlIdentifierEscaper($config->prefix())),
        );

        self::assertSame('`users`.`id`', $driver->protect_identifiers('users.id'));
        self::assertSame('`users`.`id` AS `user_id`', $driver->protect_identifiers('users.id AS user_id'));
    }

    public function testEscapeAndProtectIdentifiersUseSqliteQuotesForSqliteDialect(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
            'prefix' => '',
        ]);

        $driver = new PdoDatabaseDriver(
            connection: new PdoConnection($config),
            identifierEscaper: new SqliteIdentifierEscaper($config->prefix()),
            identifierProtector: new IdentifierProtector(new SqliteIdentifierEscaper($config->prefix())),
        );

        self::assertSame('"users"."id"', $driver->protect_identifiers('users.id'));
        self::assertSame('"users"."id"', $driver->escape_identifiers('users.id'));
    }
}
