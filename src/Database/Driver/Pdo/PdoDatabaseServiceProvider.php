<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\DatabaseDialect;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\DatabaseDriverRegistry;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteSchemaGrammar;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;

final class PdoDatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(PdoConnection::class, static function (ContainerInterface $container): PdoConnection {
            $connection = $container->get(ConnectionInterface::class);

            if (!$connection instanceof PdoConnection) {
                throw DatabaseException::invalidConfiguration(
                    'Current database connection is not a PDO connection.',
                );
            }

            return $connection;
        });

        $container->singleton(PdoDatabaseDriver::class, static function (ContainerInterface $container): PdoDatabaseDriver {
            $connection = $container->get(PdoConnection::class);
            $config = $container->get(DatabaseConfig::class);

            return new PdoDatabaseDriver(
                connection: $connection,
                config: $config,
            );
        });

        $registry = $container->get(DatabaseDriverRegistry::class);

        $registry->registerDriver(
            Driver::Pdo,
            static function (
                ConnectionInterface $connection,
                DatabaseConfig $config,
                ContainerInterface $container,
            ): PdoDatabaseDriver {
                unset($container);

                if (!$connection instanceof PdoConnection) {
                    throw DatabaseException::invalidConfiguration(
                        'Driver "pdo" requires PdoConnection.',
                    );
                }

                return new PdoDatabaseDriver(
                    connection: $connection,
                    config: $config,
                );
            },
        );

        $registry->registerSchemaGrammar(
            Driver::Pdo,
            static function (
                DatabaseConfig $config,
                ContainerInterface $container,
            ): SchemaGrammarInterface {
                return match ($config->dialect()) {
                    DatabaseDialect::Mysql => self::resolveMysqlGrammar($config, $container),
                    DatabaseDialect::Sqlite => self::resolveSqliteGrammar($config, $container),
                    default => throw DatabaseException::invalidConfiguration(
                        'Schema grammar for driver "pdo" requires explicit supported dialect. Currently supported PDO dialects: mysql, sqlite.',
                    ),
                };
            },
        );
    }

    private static function resolveMysqlGrammar(DatabaseConfig $config, ContainerInterface $container): SchemaGrammarInterface
    {
        if (!PdoDsnResolver::isMysql($config)) {
            throw DatabaseException::invalidConfiguration(
                'PDO dialect "mysql" requires DSN with "mysql:" prefix (explicit or fallback).',
            );
        }

        return $container->get(MysqlSchemaGrammar::class);
    }

    private static function resolveSqliteGrammar(DatabaseConfig $config, ContainerInterface $container): SchemaGrammarInterface
    {
        if (!PdoDsnResolver::isSqlite($config)) {
            throw DatabaseException::invalidConfiguration(
                'PDO dialect "sqlite" requires DSN with "sqlite:" prefix.',
            );
        }

        return $container->get(SqliteSchemaGrammar::class);
    }
}
