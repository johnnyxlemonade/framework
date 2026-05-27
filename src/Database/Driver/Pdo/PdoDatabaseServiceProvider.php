<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\DatabaseDriverRegistry;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSqlEscaper;
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

        $container->singleton(MysqlSqlEscaper::class, static function (ContainerInterface $container): MysqlSqlEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new MysqlSqlEscaper($config);
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
                $dsn = $config->dsn();

                if (!is_string($dsn) || !str_starts_with(strtolower($dsn), 'mysql:')) {
                    throw DatabaseException::invalidConfiguration(
                        'Schema grammar for driver "pdo" is supported only for MySQL DSN (mysql:...).',
                    );
                }

                $grammar = $container->get(MysqlSchemaGrammar::class);

                return $grammar;
            },
        );
    }
}
