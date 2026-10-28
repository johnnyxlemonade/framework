<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config\Definition\ConfigDefinitionRegistry;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Config\DatabaseConfigDefinition;
use Lemonade\Framework\Database\Config\DatabaseConfigResolver;
use Lemonade\Framework\Database\Config\DatabaseRuntimeConfig;
use Lemonade\Framework\Database\Connection\ConnectionFactory;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Instrumentation\Internal\DatabaseInstrumentation;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;

/**
 * Registers the configured connection, driver, query facade, and schema services.
 */
final readonly class DatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers the database capability with the framework container or registry.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        if (!$container->isBound(DatabaseInstrumentation::class)) {
            $container->singleton(DatabaseInstrumentation::class, DatabaseInstrumentation::class);
        }
        $container->set(ConnectionFactory::class, static function (ContainerInterface $container): ConnectionFactory {
            return new ConnectionFactory(
                instrumentation: $container->get(DatabaseInstrumentation::class),
            );
        });
        $container->singleton(DatabaseDriverRegistry::class, DatabaseDriverRegistry::class);
        $container->singleton(DatabaseRuntimeConfig::class, static function (ContainerInterface $container): DatabaseRuntimeConfig {
            return $container
                ->get(DatabaseConfigResolver::class)
                ->resolve(...$container->get(ConfigDefinitionRegistry::class)->typedEntriesFor(
                    DatabaseConfigDefinition::moduleKey(),
                    DatabaseConfigDefinition::class,
                ));
        });

        $container->singleton(DatabaseConfig::class, static function (ContainerInterface $container): DatabaseConfig {
            $config = $container->get(DatabaseRuntimeConfig::class);
            $connectionName = self::resolveConnectionName($config);
            $database = $config->connections[$connectionName] ?? null;

            if (!$database instanceof DatabaseConfig) {
                throw DatabaseException::invalidConfiguration(
                    sprintf('Database connection [%s] is not configured.', $connectionName),
                );
            }

            return $database;
        });

        $container->singleton(ConnectionInterface::class, static function (ContainerInterface $container): ConnectionInterface {
            $factory = $container->get(ConnectionFactory::class);
            $config = $container->get(DatabaseConfig::class);

            return $factory->create($config);
        });

        $container->singleton(DatabaseDriverInterface::class, static function (ContainerInterface $container): DatabaseDriverInterface {
            $config = $container->get(DatabaseConfig::class);
            $connection = $container->get(ConnectionInterface::class);
            $registry = $container->get(DatabaseDriverRegistry::class);

            return $registry->resolveDriver(
                driver: $config->driver(),
                connection: $connection,
                config: $config,
                container: $container,
            );
        });

        $container->singleton(Database::class, static function (ContainerInterface $container): Database {
            $connection = $container->get(ConnectionInterface::class);
            $driver = $container->get(DatabaseDriverInterface::class);

            return new Database(
                connection: $connection,
                driver: $driver,
            );
        });

        $container->set(SchemaGrammarInterface::class, static function (ContainerInterface $container): SchemaGrammarInterface {
            $config = $container->get(DatabaseConfig::class);
            $registry = $container->get(DatabaseDriverRegistry::class);

            return $registry->resolveSchemaGrammar(
                driver: $config->driver(),
                config: $config,
                container: $container,
            );
        });
    }

    /**
     * Resolves the effective value from the configured database dependencies and input.
     */
    private static function resolveConnectionName(DatabaseRuntimeConfig $config): string
    {
        $connectionName = $config->defaultConnection;

        if ($connectionName === null || $connectionName === '') {
            return 'default';
        }

        return $connectionName;
    }
}
