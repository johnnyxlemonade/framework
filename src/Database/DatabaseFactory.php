<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Connection\ConnectionFactory;
use Lemonade\Framework\Database\Connection\DatabaseConfig;

/**
 * Builds a Database facade with a matching connection and registered driver implementation.
 */
final readonly class DatabaseFactory
{
    /**
     * Initializes this DatabaseFactory with the collaborators and state required by its database contract.
     */
    public function __construct(
        private ConnectionFactory $connectionFactory,
        private DatabaseDriverRegistry $registry,
        private ContainerInterface $container,
    ) {
    }

    /**
     * Builds the requested database value while preserving its framework contract.
     */
    public function create(DatabaseConfig $config): Database
    {
        $connection = $this->connectionFactory->create($config);

        return new Database(
            connection: $connection,
            driver: $this->registry->resolveDriver(
                driver: $config->driver(),
                connection: $connection,
                config: $config,
                container: $this->container,
            ),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    public function createFromArray(array $config): Database
    {
        return $this->create(DatabaseConfig::fromArray($config));
    }
}
