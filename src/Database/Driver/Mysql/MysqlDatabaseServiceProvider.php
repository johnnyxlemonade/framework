<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\Driver;
use Lemonade\Framework\Database\DatabaseDriverRegistry;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;
use Lemonade\Framework\Database\Sql\IdentifierProtector;

/**
 * Registers MySQL-specific connection adapters, driver services, and schema grammar factories.
 */
final readonly class MysqlDatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Binds MySQL services and registers factories that reject non-MySQL configured connections.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->set(MysqlIdentifierEscaper::class, static function (ContainerInterface $container): MysqlIdentifierEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new MysqlIdentifierEscaper($config->prefix());
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
