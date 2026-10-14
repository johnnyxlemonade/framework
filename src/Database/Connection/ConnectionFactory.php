<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

use Lemonade\Framework\Database\Driver\Mysql\MysqlConnection;
use Lemonade\Framework\Database\Driver\Odbc\OdbcConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Observability\Benchmark\Benchmark;

/**
 * Selects the concrete connection implementation for a resolved driver configuration.
 */
final readonly class ConnectionFactory
{
    /**
     * Creates a factory with shared benchmark instrumentation and optional query-detail capture.
     */
    public function __construct(
        private Benchmark $benchmark,
        private bool $captureQueryDetails = false,
    ) {
    }

    /**
     * Instantiates the connection adapter selected by the resolved driver enum.
     */
    public function create(DatabaseConfig $config): ConnectionInterface
    {
        return match ($config->driver()) {
            Driver::Mysql => new MysqlConnection($config, $this->benchmark, $this->captureQueryDetails),
            Driver::Odbc => new OdbcConnection($config, $this->benchmark, $this->captureQueryDetails),
            Driver::Pdo => new PdoConnection($config, $this->benchmark, $this->captureQueryDetails),
        };
    }
}
