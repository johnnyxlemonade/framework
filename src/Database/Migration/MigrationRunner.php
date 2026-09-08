<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

use Lemonade\Framework\Database\Schema\Schema;

/**
 * Orchestrates pending migrations over the registry, state history, and schema API.
 *
 * State is recorded only after a migration completes successfully. The runner does
 * not wrap schema migrations in an automatic database transaction.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly MigrationStateRepository $state,
        private readonly Schema $schema,
    ) {}

    /**
     * Runs pending migrations in deterministic identifier order.
     *
     * Returns only migrations executed by this invocation. Execution stops when a
     * migration fails, so subsequent migrations are not started.
     *
     * @return list<string>
     */
    public function migrate(): array
    {
        $this->state->ensureTable();
        $applied = array_fill_keys($this->state->applied(), true);
        $executed = [];

        foreach ($this->registry->identifiers() as $identifier) {
            if (isset($applied[$identifier])) {
                continue;
            }

            $migration = $this->registry->get($identifier);
            $migration->up($this->schema);
            $this->state->record($identifier);
            $executed[] = $identifier;
        }

        return $executed;
    }

    /**
     * Builds the database-backed Applied/Pending status of registered migrations.
     *
     * Orphaned identifiers are applied migrations that are no longer registered.
     */
    public function status(): MigrationStatus
    {
        $this->state->ensureTable();
        $applied = array_fill_keys($this->state->applied(), true);
        $identifiers = $this->registry->identifiers();

        $registered = [];

        foreach ($identifiers as $identifier) {
            $registered[$identifier] = isset($applied[$identifier])
                ? MigrationState::Applied
                : MigrationState::Pending;
            unset($applied[$identifier]);
        }

        $orphaned = array_keys($applied);
        sort($orphaned, SORT_STRING);

        return new MigrationStatus($registered, $orphaned);
    }
}
