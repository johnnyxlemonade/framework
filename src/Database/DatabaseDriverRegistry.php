<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;

final class DatabaseDriverRegistry
{
    /**
     * @var array<string, callable(ConnectionInterface, DatabaseConfig, ContainerInterface):DatabaseDriverInterface>
     */
    private array $driverFactories = [];

    /**
     * @var array<string, callable(DatabaseConfig, ContainerInterface):SchemaGrammarInterface>
     */
    private array $schemaGrammarFactories = [];

    /**
     * @param callable(ConnectionInterface, DatabaseConfig, ContainerInterface):DatabaseDriverInterface $factory
     */
    public function registerDriver(Driver $driver, callable $factory): void
    {
        $this->driverFactories[$driver->value] = $factory;
    }

    /**
     * @param callable(DatabaseConfig, ContainerInterface):SchemaGrammarInterface $factory
     */
    public function registerSchemaGrammar(Driver $driver, callable $factory): void
    {
        $this->schemaGrammarFactories[$driver->value] = $factory;
    }

    public function resolveDriver(
        Driver $driver,
        ConnectionInterface $connection,
        DatabaseConfig $config,
        ContainerInterface $container,
    ): DatabaseDriverInterface {
        $factory = $this->driverFactories[$driver->value] ?? null;

        if ($factory === null) {
            throw DatabaseException::unsupportedDriver($driver->value);
        }

        return $factory($connection, $config, $container);
    }

    public function resolveSchemaGrammar(
        Driver $driver,
        DatabaseConfig $config,
        ContainerInterface $container,
    ): SchemaGrammarInterface {
        $factory = $this->schemaGrammarFactories[$driver->value] ?? null;

        if ($factory === null) {
            throw DatabaseException::invalidConfiguration(
                sprintf(
                    'Schema grammar for database driver [%s] is not registered.',
                    $driver->value,
                ),
            );
        }

        return $factory($config, $container);
    }
}
