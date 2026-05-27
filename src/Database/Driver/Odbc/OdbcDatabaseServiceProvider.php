<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\DatabaseDriverRegistry;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;

final class OdbcDatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(OdbcConnection::class, static function (ContainerInterface $container): OdbcConnection {
            $connection = $container->get(ConnectionInterface::class);

            if (!$connection instanceof OdbcConnection) {
                throw DatabaseException::invalidConfiguration(
                    'Current database connection is not an ODBC connection.',
                );
            }

            return $connection;
        });

        $container->singleton(OdbcDatabaseDriver::class, static function (ContainerInterface $container): OdbcDatabaseDriver {
            $connection = $container->get(OdbcConnection::class);
            $config = $container->get(DatabaseConfig::class);

            return new OdbcDatabaseDriver(
                connection: $connection,
                config: $config,
            );
        });

        $container->singleton(OdbcSqlEscaper::class, static function (ContainerInterface $container): OdbcSqlEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new OdbcSqlEscaper($config);
        });

        $container->singleton(OdbcSchemaGrammar::class, static function (ContainerInterface $container): OdbcSchemaGrammar {
            $escaper = $container->get(OdbcSqlEscaper::class);
            $config = $container->get(DatabaseConfig::class);

            return new OdbcSchemaGrammar(
                escaper: $escaper,
                config: $config,
            );
        });

        $registry = $container->get(DatabaseDriverRegistry::class);

        $registry->registerDriver(
            Driver::Odbc,
            static function (
                ConnectionInterface $connection,
                DatabaseConfig $config,
                ContainerInterface $container,
            ): OdbcDatabaseDriver {
                unset($container);

                if (!$connection instanceof OdbcConnection) {
                    throw DatabaseException::invalidConfiguration(
                        'Driver "odbc" requires OdbcConnection.',
                    );
                }

                return new OdbcDatabaseDriver(
                    connection: $connection,
                    config: $config,
                );
            },
        );

        $registry->registerSchemaGrammar(
            Driver::Odbc,
            static function (
                DatabaseConfig $config,
                ContainerInterface $container,
            ): SchemaGrammarInterface {
                unset($config);

                $grammar = $container->get(OdbcSchemaGrammar::class);

                return $grammar;
            },
        );
    }
}
