<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Sqlite;

use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\Connection\DatabaseConfig;

final class SqliteDatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(SqliteIdentifierEscaper::class, static function (ContainerInterface $container): SqliteIdentifierEscaper {
            $config = $container->get(DatabaseConfig::class);

            return new SqliteIdentifierEscaper($config->prefix());
        });

        $container->singleton(SqliteSqlEscaper::class, static function (ContainerInterface $container): SqliteSqlEscaper {
            return new SqliteSqlEscaper(
                $container->get(SqliteIdentifierEscaper::class),
            );
        });

        $container->singleton(SqliteSchemaGrammar::class, static function (ContainerInterface $container): SqliteSchemaGrammar {
            $escaper = $container->get(SqliteSqlEscaper::class);
            $config = $container->get(DatabaseConfig::class);

            return new SqliteSchemaGrammar(
                escaper: $escaper,
                config: $config,
            );
        });
    }
}
