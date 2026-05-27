<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Connection\ConnectionFactory;
use Lemonade\Framework\Database\Connection\DatabaseConfig;

final class DatabaseFactory
{
    public function __construct(
        private readonly ConnectionFactory $connectionFactory,
        private readonly DatabaseDriverRegistry $registry,
        private readonly ContainerInterface $container,
    ) {}

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
