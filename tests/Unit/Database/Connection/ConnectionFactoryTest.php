<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Connection;

use Lemonade\Framework\Database\Connection\ConnectionFactory;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use PHPUnit\Framework\TestCase;

final class ConnectionFactoryTest extends TestCase
{
    public function testCreateReturnsPdoConnectionForPdoDriver(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'pdo',
            'dsn' => 'sqlite::memory:',
        ]);

        $factory = new ConnectionFactory(new DatabaseInstrumentation());
        $connection = $factory->create($config);

        self::assertInstanceOf(PdoConnection::class, $connection);
    }
}
