<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Internal;

use Lemonade\Framework\Database\Connection\ConnectionInterface;
use Lemonade\Framework\Database\DatabaseDriverInterface;

/**
 * Rejects missing or pre-lease queue tables without attempting an implicit schema migration.
 *
 * @internal
 */
final readonly class DatabaseQueueSchemaValidator
{
    /**
     * Creates a validator over the configured queue database connection.
     */
    public function __construct(
        private DatabaseDriverInterface $db,
        private ConnectionInterface $connection,
        private DatabaseQueueDialect $dialect,
    ) {
    }

    /**
     * Verifies that both queue tables expose every column required by the authenticated lease lifecycle.
     */
    public function assertCompatible(string $table, string $failedTable): void
    {
        $this->assertActiveCompatible($table);
        $this->assertFailedCompatible($failedTable);
    }

    /**
     * Verifies that an existing active queue table exposes the complete lease-aware row contract.
     */
    public function assertActiveCompatible(string $table): void
    {
        $this->assertColumns(
            $table,
            [
                'id',
                'queue_name',
                'payload',
                'attempts',
                'available_at',
                'reserved_at',
                'reservation_token',
                'reserved_until',
                'created_at',
                'updated_at',
            ],
        );
    }

    /**
     * Verifies that an existing failed-job table can preserve terminal delivery diagnostics.
     */
    public function assertFailedCompatible(string $failedTable): void
    {
        $this->assertColumns(
            $failedTable,
            [
                'id',
                'job_id',
                'queue_name',
                'payload',
                'attempts',
                'error_message',
                'failed_at',
            ],
        );
    }

    /**
     * @param non-empty-list<string> $columns
     */
    private function assertColumns(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) {
            throw new \RuntimeException(sprintf(
                'Queue table "%s" is missing; run queue:install before using the database queue.',
                $table,
            ));
        }

        $protectedTable = $this->db->protect_identifiers($table, true, null, false);

        if ($this->dialect->isSqlite()) {
            $rows = $this->connection->select(sprintf('PRAGMA table_info(%s)', $protectedTable));
            $available = [];
            foreach ($rows as $row) {
                $name = $row['name'] ?? null;
                if (is_string($name)) {
                    $available[] = $name;
                }
            }

            $missing = array_diff($columns, $available);
            if ($missing !== []) {
                $this->incompatible($table);
            }

            return;
        }

        $protectedColumns = array_map(
            fn(string $column): string => $this->db->protect_identifiers($column, false, null, false),
            $columns,
        );

        try {
            $this->connection->select(sprintf(
                'SELECT %s FROM %s WHERE 1 = 0',
                implode(', ', $protectedColumns),
                $protectedTable,
            ));
        } catch (\Throwable $exception) {
            $this->incompatible($table, $exception);
        }
    }

    private function incompatible(string $table, ?\Throwable $previous = null): never
    {
        throw new \RuntimeException(sprintf(
            'Queue table "%s" is incompatible with reservation leases; stop legacy workers and run the documented explicit queue migration.',
            $table,
        ), 0, $previous);
    }
}
