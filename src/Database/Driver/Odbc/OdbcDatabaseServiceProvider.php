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
use Lemonade\Framework\Database\Sql\IdentifierProtector;

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
            $identifierEscaper = $container->get(OdbcIdentifierEscaper::class);
            $identifierProtector = new IdentifierProtector($identifierEscaper);

            return new OdbcDatabaseDriver(
                connection: $connection,
                identifierEscaper: $identifierEscaper,
                identifierProtector: $identifierProtector,
            );
        });

        $container->singleton(OdbcIdentifierEscaper::class, static function (ContainerInterface $container): OdbcIdentifierEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new OdbcIdentifierEscaper($config->prefix());
        });

        $container->singleton(OdbcSqlEscaper::class, static function (ContainerInterface $container): OdbcSqlEscaper {
            return new OdbcSqlEscaper(
                $container->get(OdbcIdentifierEscaper::class),
            );
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
                unset($config);

                if (!$connection instanceof OdbcConnection) {
                    throw DatabaseException::invalidConfiguration(
                        'Driver "odbc" requires OdbcConnection.',
                    );
                }

                $identifierEscaper = $container->get(OdbcIdentifierEscaper::class);
                $identifierProtector = new IdentifierProtector($identifierEscaper);

                return new OdbcDatabaseDriver(
                    connection: $connection,
                    identifierEscaper: $identifierEscaper,
                    identifierProtector: $identifierProtector,
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
