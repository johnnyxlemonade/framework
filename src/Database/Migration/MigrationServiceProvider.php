<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Migration\Cli\MigrateCommand;
use Lemonade\Framework\Database\Migration\Cli\MigrationStatusCommand;
use Lemonade\Framework\Database\Schema\Schema;

/**
 * Registers migration infrastructure and CLI commands.
 *
 * Database-backed services remain lazy so normal framework and CLI bootstrap does
 * not require a configured database connection.
 */
final class MigrationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        if (!$container->isBound(MigrationRegistry::class)) {
            $container->singleton(MigrationRegistry::class, static fn(ContainerInterface $container): MigrationRegistry => new MigrationRegistry($container));
        }
        $container->singleton(MigrationStateRepository::class, static fn(ContainerInterface $container): MigrationStateRepository => new MigrationStateRepository(
            $container->get(DatabaseDriverInterface::class),
            $container->get(Schema::class),
        ));
        $container->singleton(MigrationRunner::class, static fn(ContainerInterface $container): MigrationRunner => new MigrationRunner(
            $container->get(MigrationRegistry::class),
            $container->get(MigrationStateRepository::class),
            $container->get(Schema::class),
        ));
        $container->singleton(MigrateCommand::class, MigrateCommand::class);
        $container->singleton(MigrationStatusCommand::class, MigrationStatusCommand::class);

        if (!$container->isBound(CommandRegistry::class)) {
            return;
        }

        $commands = $container->get(CommandRegistry::class);
        $commands->register(MigrateCommand::class);
        $commands->register(MigrationStatusCommand::class);
    }
}
