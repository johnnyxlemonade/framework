<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\ContainerBuilderInterface;
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
    public function register(ContainerBuilderInterface $container): void
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
        if (!$container->isBound(CommandRegistry::class)) {
            return;
        }

        $commands = $container->get(CommandRegistry::class);
        $commands->registerDefinition(new CommandDefinition(
            name: 'database:migrate',
            commandClass: MigrateCommand::class,
            description: 'Runs pending database migrations.',
        ));
        $commands->registerDefinition(new CommandDefinition(
            name: 'database:migrate:status',
            commandClass: MigrationStatusCommand::class,
            description: 'Shows database migration status.',
        ));
    }
}
