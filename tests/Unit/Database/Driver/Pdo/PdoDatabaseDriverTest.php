<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Pdo;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoDatabaseDriver;
use PHPUnit\Framework\TestCase;

final class PdoDatabaseDriverTest extends TestCase
{
    public function testEscapeIdentifiersUsesMysqlCompatibleBackticks(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dialect' => 'mysql',
            'dsn' => 'sqlite::memory:',
        ]);

        $driver = new PdoDatabaseDriver(
            connection: new PdoConnection($config),
            config: $config,
        );

        self::assertSame('`table`', $driver->escape_identifiers('table'));
        self::assertSame('`we``ird`', $driver->escape_identifiers('we`ird'));
        self::assertSame('*', $driver->escape_identifiers('*'));
    }
}
