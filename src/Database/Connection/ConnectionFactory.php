<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

use Lemonade\Framework\Database\Driver\Mysql\MysqlConnection;
use Lemonade\Framework\Database\Driver\Odbc\OdbcConnection;
use Lemonade\Framework\Database\Exception\DatabaseException;

final class ConnectionFactory
{
    public function create(DatabaseConfig $config): ConnectionInterface
    {
        return match ($config->driver()) {
            Driver::Mysql => new MysqlConnection($config),
            Driver::Odbc => new OdbcConnection($config),
            Driver::Pdo => throw DatabaseException::unsupportedDriver(Driver::Pdo->value),
        };
    }
}
