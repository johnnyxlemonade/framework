<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Migration;

/**
 * Immutable snapshot of the migration status known to the framework.
 */
final class MigrationStatus
{
    /**
     * @param array<string, MigrationState> $registered
     * @param list<string> $orphaned
     * @param bool $databaseAvailable Whether the database-backed history was available for verification.
     */
    public function __construct(
        private readonly array $registered,
        private readonly array $orphaned,
        private readonly bool $databaseAvailable = true,
    ) {}

    /**
     * Returns the registered migrations and their current states.
     *
     * @return array<string, MigrationState>
     */
    public function registered(): array
    {
        return $this->registered;
    }

    /**
     * Returns applied migration identifiers that are no longer registered.
     *
     * @return list<string>
     */
    public function orphaned(): array
    {
        return $this->orphaned;
    }

    /**
     * Indicates whether the database-backed migration history was available.
     */
    public function databaseAvailable(): bool
    {
        return $this->databaseAvailable;
    }
}
