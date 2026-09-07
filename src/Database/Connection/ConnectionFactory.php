<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

use Lemonade\Framework\Database\Driver\Mysql\MysqlConnection;
use Lemonade\Framework\Database\Driver\Odbc\OdbcConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Observability\Benchmark\Benchmark;

final class ConnectionFactory
{
    public function __construct(
        private readonly Benchmark $benchmark,
        private readonly bool $captureQueryDetails = false,
    ) {}

    public function create(DatabaseConfig $config): ConnectionInterface
    {
        return match ($config->driver()) {
            Driver::Mysql => new MysqlConnection($config, $this->benchmark, $this->captureQueryDetails),
            Driver::Odbc => new OdbcConnection($config, $this->benchmark, $this->captureQueryDetails),
            Driver::Pdo => new PdoConnection($config, $this->benchmark, $this->captureQueryDetails),
        };
    }
}
