<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration\Cli;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Database\Exception\DatabaseException;
use Lemonade\Framework\Database\Migration\MigrationRegistry;
use Lemonade\Framework\Database\Migration\MigrationRunner;
use Lemonade\Framework\Database\Migration\MigrationState;
use Lemonade\Framework\Database\Migration\MigrationStatus;

use function fwrite;
use function is_resource;

/**
 * CLI command that displays the migration status.
 *
 * When database history is unavailable, it reports registry-only Unknown states
 * and returns a non-zero exit code because the status could not be verified.
 */
final class MigrationStatusCommand implements CommandInterface
{
    /** @var resource */
    private $stdout;
    /** @var resource */
    private $stderr;

    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly ContainerInterface $container,
        mixed $stdout = null,
        mixed $stderr = null,
    ) {
        if ($stdout !== null && !is_resource($stdout)) {
            throw new \InvalidArgumentException('MigrationStatusCommand stdout must be a valid resource.');
        }
        if ($stderr !== null && !is_resource($stderr)) {
            throw new \InvalidArgumentException('MigrationStatusCommand stderr must be a valid resource.');
        }

        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
    }

    public function name(): string
    {
        return 'database:migrate:status';
    }

    public function description(): string
    {
        return 'Shows database migration status.';
    }

    public function run(array $args): int
    {
        unset($args);

        try {
            $status = $this->resolveStatus();
            foreach ($status->registered() as $identifier => $state) {
                fwrite(
                    $this->stdout,
                    sprintf("%s  %s\n", strtoupper($state->value), $identifier),
                );
            }

            foreach ($status->orphaned() as $identifier) {
                fwrite($this->stdout, sprintf("orphaned  %s (applied but not registered)\n", $identifier));
            }

            if ($status->databaseAvailable()) {
                return 0;
            }

            fwrite($this->stderr, "Database state unavailable: no database connection is configured.\n");

            return 1;
        } catch (\Throwable $exception) {
            fwrite($this->stderr, sprintf("Migration status failed: %s\n", $exception->getMessage()));

            return 1;
        }
    }

    private function resolveStatus(): MigrationStatus
    {
        try {
            return $this->container->get(MigrationRunner::class)->status();
        } catch (DatabaseException $exception) {
            if (!str_contains($exception->getMessage(), 'Database connection [')
                || !str_contains($exception->getMessage(), 'is not configured.')) {
                throw $exception;
            }

            $registered = [];
            foreach ($this->registry->identifiers() as $identifier) {
                $registered[$identifier] = MigrationState::Unknown;
            }

            return new MigrationStatus($registered, [], false);
        }
    }
}
