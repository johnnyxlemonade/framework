<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\Config;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionFactory;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Schema\Schema;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;

final class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ConnectionFactory::class, ConnectionFactory::class);
        $container->singleton(DatabaseFactory::class, DatabaseFactory::class);
        $container->singleton(DatabaseDriverRegistry::class, DatabaseDriverRegistry::class);

        $container->singleton(DatabaseConfig::class, static function (ContainerInterface $container): DatabaseConfig {
            /** @var Config $config */
            $config = $container->get(Config::class);

            $connectionName = self::resolveConnectionName($config);
            $database = self::resolveConnectionConfig($config, $connectionName);

            if ($database === []) {
                throw DatabaseException::invalidConfiguration(
                    sprintf('Database connection [%s] is not configured.', $connectionName),
                );
            }

            return DatabaseConfig::fromArray($database);
        });

        $container->singleton(ConnectionInterface::class, static function (ContainerInterface $container): ConnectionInterface {
            /** @var ConnectionFactory $factory */
            $factory = $container->get(ConnectionFactory::class);

            /** @var DatabaseConfig $config */
            $config = $container->get(DatabaseConfig::class);

            return $factory->create($config);
        });

        $container->singleton(Database::class, static function (ContainerInterface $container): Database {
            /** @var DatabaseFactory $factory */
            $factory = $container->get(DatabaseFactory::class);

            /** @var DatabaseConfig $config */
            $config = $container->get(DatabaseConfig::class);

            return $factory->create($config);
        });

        $container->singleton(DatabaseDriverInterface::class, static function (ContainerInterface $container): DatabaseDriverInterface {
            /** @var DatabaseConfig $config */
            $config = $container->get(DatabaseConfig::class);

            /** @var ConnectionInterface $connection */
            $connection = $container->get(ConnectionInterface::class);

            /** @var DatabaseDriverRegistry $registry */
            $registry = $container->get(DatabaseDriverRegistry::class);

            return $registry->resolveDriver(
                driver: $config->driver(),
                connection: $connection,
                config: $config,
                container: $container,
            );
        });

        $container->singleton(SchemaGrammarInterface::class, static function (ContainerInterface $container): SchemaGrammarInterface {
            /** @var DatabaseConfig $config */
            $config = $container->get(DatabaseConfig::class);

            /** @var DatabaseDriverRegistry $registry */
            $registry = $container->get(DatabaseDriverRegistry::class);

            return $registry->resolveSchemaGrammar(
                driver: $config->driver(),
                config: $config,
                container: $container,
            );
        });

        $container->singleton(SchemaCompiler::class, static function (ContainerInterface $container): SchemaCompiler {
            /** @var SchemaGrammarInterface $grammar */
            $grammar = $container->get(SchemaGrammarInterface::class);

            return new SchemaCompiler(
                grammar: $grammar,
            );
        });

        $container->singleton(Schema::class, static function (ContainerInterface $container): Schema {
            /** @var SchemaCompiler $compiler */
            $compiler = $container->get(SchemaCompiler::class);

            /** @var DatabaseDriverInterface $databaseDriver */
            $databaseDriver = $container->get(DatabaseDriverInterface::class);

            return new Schema(
                compiler: $compiler,
                db: $databaseDriver,
            );
        });
    }

    private static function resolveConnectionName(Config $config): string
    {
        $connectionName = $config->string('database.default');

        if ($connectionName === null || $connectionName === '') {
            $connectionName = $config->string('default');
        }

        if ($connectionName === null || $connectionName === '') {
            return 'default';
        }

        return $connectionName;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveConnectionConfig(Config $config, string $connectionName): array
    {
        $database = $config->array("database.connections.{$connectionName}");

        if ($database !== []) {
            return self::normalizeAssoc($database);
        }

        return self::normalizeAssoc($config->array("connections.{$connectionName}"));
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private static function normalizeAssoc(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
