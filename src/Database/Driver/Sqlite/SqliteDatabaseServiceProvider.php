<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Sqlite;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;

/**
 * Registers SQLite grammar and identifier services for PDO-backed SQLite connections.
 */
final readonly class SqliteDatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers the database capability with the framework container or registry.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->set(SqliteIdentifierEscaper::class, static function (ContainerInterface $container): SqliteIdentifierEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new SqliteIdentifierEscaper($config->prefix());
        });
    }
}
