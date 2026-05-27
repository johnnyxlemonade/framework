<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Mysql;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Mysql\MysqlConnection;
use mysqli;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MysqlConnectionTest extends TestCase
{
    public function testConfigureSessionStrictTrueUsesIdempotentStrictModeExpression(): void
    {
        $connection = new MysqlConnection(DatabaseConfig::fromArray([
            'driver' => 'mysql',
            'strict' => true,
        ]));

        $mysqli = $this->createMock(mysqli::class);
        $mysqli->expects(self::once())
            ->method('query')
            ->with(
                "SET SESSION sql_mode = TRIM(BOTH ',' FROM IF("
                . "FIND_IN_SET('STRICT_TRANS_TABLES', REPLACE(@@sql_mode, ' ', '')) > 0, "
                . '@@sql_mode, '
                . "CONCAT_WS(',', @@sql_mode, 'STRICT_TRANS_TABLES')"
                . '))',
            )
            ->willReturn(true);

        $method = new ReflectionMethod(MysqlConnection::class, 'configureSession');
        $method->invoke($connection, $mysqli);
    }

    public function testConfigureSessionStrictFalseRemovesStrictAndOnlyFullGroupByModes(): void
    {
        $connection = new MysqlConnection(DatabaseConfig::fromArray([
            'driver' => 'mysql',
            'strict' => false,
        ]));

        $mysqli = $this->createMock(mysqli::class);
        $mysqli->expects(self::once())
            ->method('query')
            ->with(
                "SET SESSION sql_mode = TRIM(BOTH ',' FROM "
                . 'REPLACE(REPLACE(REPLACE('
                . "CONCAT(',', REPLACE(@@sql_mode, ' ', ''), ','), "
                . "',STRICT_TRANS_TABLES,', ','), "
                . "',STRICT_ALL_TABLES,', ','), "
                . "',ONLY_FULL_GROUP_BY,', ','))",
            )
            ->willReturn(true);

        $method = new ReflectionMethod(MysqlConnection::class, 'configureSession');
        $method->invoke($connection, $mysqli);
    }

    public function testConfigureSessionWithCollationRunsSetNamesBeforeStrictHandling(): void
    {
        $connection = new MysqlConnection(DatabaseConfig::fromArray([
            'driver' => 'mysql',
            'strict' => false,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]));

        $expected = [
            'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            "SET SESSION sql_mode = TRIM(BOTH ',' FROM "
            . 'REPLACE(REPLACE(REPLACE('
            . "CONCAT(',', REPLACE(@@sql_mode, ' ', ''), ','), "
            . "',STRICT_TRANS_TABLES,', ','), "
            . "',STRICT_ALL_TABLES,', ','), "
            . "',ONLY_FULL_GROUP_BY,', ','))",
        ];

        $mysqli = $this->createMock(mysqli::class);
        $mysqli->expects(self::exactly(2))
            ->method('query')
            ->willReturnCallback(static function (string $sql) use (&$expected): bool {
                $next = array_shift($expected);
                TestCase::assertIsString($next);
                TestCase::assertSame($next, $sql);

                return true;
            });

        $method = new ReflectionMethod(MysqlConnection::class, 'configureSession');
        $method->invoke($connection, $mysqli);
        self::assertSame([], $expected);
    }
}
