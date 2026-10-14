<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration\Cli;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Migration\MigrationRunner;

use function fwrite;
use function is_resource;

/**
 * CLI command that runs pending database migrations.
 *
 * The migration runner is resolved lazily when the command is executed.
 */
final readonly class MigrateCommand implements CommandInterface
{
    /** @var resource */
    private mixed $stdout;
    /** @var resource */
    private mixed $stderr;

    /**
     * Initializes this MigrateCommand with the collaborators and state required by its database contract.
     */
    public function __construct(
        private ContainerInterface $container,
        mixed $stdout = null,
        mixed $stderr = null,
    ) {
        if ($stdout !== null && !is_resource($stdout)) {
            throw new \InvalidArgumentException('MigrateCommand stdout must be a valid resource.');
        }
        if ($stderr !== null && !is_resource($stderr)) {
            throw new \InvalidArgumentException('MigrateCommand stderr must be a valid resource.');
        }

        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function name(): string
    {
        return 'database:migrate';
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function description(): string
    {
        return 'Runs pending database migrations.';
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function run(array $args): int
    {
        unset($args);

        try {
            $executed = $this->container->get(MigrationRunner::class)->migrate();
            if ($executed === []) {
                fwrite($this->stdout, "No pending migrations.\n");

                return 0;
            }

            foreach ($executed as $identifier) {
                fwrite($this->stdout, sprintf("Migrated: %s\n", $identifier));
            }

            return 0;
        } catch (DatabaseException $exception) {
            $message = str_contains($exception->getMessage(), 'Database connection [')
                && str_contains($exception->getMessage(), 'is not configured.')
                ? 'Cannot run migrations: no database connection is configured.'
                : 'Migration failed: ' . $exception->getMessage();
            fwrite($this->stderr, $message . "\n");

            return 1;
        } catch (\Throwable $exception) {
            fwrite($this->stderr, sprintf("Migration failed: %s\n", $exception->getMessage()));

            return 1;
        }
    }
}
