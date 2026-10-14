<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

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
 * Registers ODBC-specific driver and schema-grammar factories.
 */
final readonly class OdbcDatabaseServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers the database capability with the framework container or registry.
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->set(OdbcIdentifierEscaper::class, static function (ContainerInterface $container): OdbcIdentifierEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new OdbcIdentifierEscaper($config->prefix());
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
