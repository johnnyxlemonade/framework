<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

use Lemonade\Framework\Database\Driver\Mysql\MysqlConnection;
use Lemonade\Framework\Database\Driver\Odbc\OdbcConnection;
use Lemonade\Framework\Database\Driver\Pdo\PdoConnection;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;

/**
 * Selects the concrete connection implementation for a resolved driver configuration.
 */
final readonly class ConnectionFactory
{
    /**
     * Creates a factory with shared optional database instrumentation.
     */
    public function __construct(
        private DatabaseInstrumentation $instrumentation = new DatabaseInstrumentation(),
    ) {
    }

    /**
     * Instantiates the connection adapter selected by the resolved driver enum.
     */
    public function create(DatabaseConfig $config): ConnectionInterface
    {
        return match ($config->driver()) {
            Driver::Mysql => new MysqlConnection($config, $this->instrumentation),
            Driver::Odbc => new OdbcConnection($config, $this->instrumentation),
            Driver::Pdo => new PdoConnection($config, $this->instrumentation),
        };
    }
}
