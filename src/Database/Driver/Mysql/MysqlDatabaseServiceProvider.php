<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\DatabaseDriverRegistry;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;
use Lemonade\Framework\Database\Sql\IdentifierProtector;

final class MysqlDatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MysqlConnectionInterface::class, static function (ContainerInterface $container): MysqlConnectionInterface {
            $connection = $container->get(ConnectionInterface::class);

            if (!$connection instanceof MysqlConnectionInterface) {
                throw DatabaseException::invalidConfiguration(
                    'Current database connection is not a MySQL connection.',
                );
            }

            return $connection;
        });

        $container->singleton(MysqlDatabaseDriver::class, static function (ContainerInterface $container): MysqlDatabaseDriver {
            $connection = $container->get(MysqlConnectionInterface::class);
            $config = $container->get(DatabaseConfig::class);
            $identifierEscaper = $container->get(MysqlIdentifierEscaper::class);
            $identifierProtector = new IdentifierProtector($identifierEscaper);

            return new MysqlDatabaseDriver(
                connection: $connection,
                config: $config,
                identifierEscaper: $identifierEscaper,
                identifierProtector: $identifierProtector,
            );
        });

        $container->singleton(MysqlIdentifierEscaper::class, static function (ContainerInterface $container): MysqlIdentifierEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new MysqlIdentifierEscaper($config->prefix());
        });

        $container->singleton(MysqlSqlEscaper::class, static function (ContainerInterface $container): MysqlSqlEscaper {
            return new MysqlSqlEscaper(
                $container->get(MysqlIdentifierEscaper::class),
            );
        });

        $container->singleton(MysqlSchemaGrammar::class, static function (ContainerInterface $container): MysqlSchemaGrammar {
            $escaper = $container->get(MysqlSqlEscaper::class);
            $config = $container->get(DatabaseConfig::class);

            return new MysqlSchemaGrammar(
                escaper: $escaper,
                config: $config,
            );
        });

        $registry = $container->get(DatabaseDriverRegistry::class);

        $registry->registerDriver(
            Driver::Mysql,
            static function (
                ConnectionInterface $connection,
                DatabaseConfig $config,
                ContainerInterface $container,
            ): MysqlDatabaseDriver {
                if (!$connection instanceof MysqlConnectionInterface) {
                    throw DatabaseException::invalidConfiguration(
                        'Driver "mysql" requires MysqlConnectionInterface.',
                    );
                }

                $identifierEscaper = $container->get(MysqlIdentifierEscaper::class);
                $identifierProtector = new IdentifierProtector($identifierEscaper);

                return new MysqlDatabaseDriver(
                    connection: $connection,
                    config: $config,
                    identifierEscaper: $identifierEscaper,
                    identifierProtector: $identifierProtector,
                );
            },
        );

        $registry->registerSchemaGrammar(
            Driver::Mysql,
            static function (
                DatabaseConfig $config,
                ContainerInterface $container,
            ): SchemaGrammarInterface {
                unset($config);

                $grammar = $container->get(MysqlSchemaGrammar::class);

                return $grammar;
            },
        );
    }
}
