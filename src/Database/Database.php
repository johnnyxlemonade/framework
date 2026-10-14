<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Database\Connection\ConnectionInterface;

/**
 * Provides database operations and driver capabilities to framework consumers.
 */
final class Database
{
    /** @var list<callable():void> */
    private array $afterCommitCallbacks = [];

    private int $managedTransactionDepth = 0;
    /**
     * Creates a facade over the selected connection and its SQL driver capabilities.
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DatabaseDriverInterface $driver,
    ) {
    }

    /**
     * Returns the underlying connection for integrations that require connection-level operations.
     */
    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Reads the complete result set as associative rows using bound values rather than interpolation.
     *
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->connection->select($sql, $bindings);
    }

    /**
     * Executes a non-result statement and reports the driver's affected row count.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->connection->statement($sql, $bindings);
    }

    /**
     * Streams rows without materializing the result set; callers must consume the generator before reusing the connection.
     *
     * @param array<int|string, mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array $bindings = []): \Generator
    {
        return $this->connection->cursor($sql, $bindings);
    }

    /**
     * Runs a callback in the connection transaction boundary and invokes queued after-commit callbacks only after the outer commit.
     *
     * @template T
     * @param callable(ConnectionInterface): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->connection->inTransaction();
        if ($this->managedTransactionDepth === 0 && !$ownsTransaction) {
            throw new \LogicException('After-commit callbacks require a transaction started through Database.');
        }
        ++$this->managedTransactionDepth;

        try {
            $result = $this->connection->transaction($callback);
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->afterCommitCallbacks = [];
            }

            throw $exception;
        } finally {
            --$this->managedTransactionDepth;
        }

        if ($ownsTransaction) {
            $callbacks = $this->afterCommitCallbacks;
            $this->afterCommitCallbacks = [];

            foreach ($callbacks as $afterCommit) {
                $afterCommit();
            }
        }

        return $result;
    }

    /**
     * Schedules a side effect after a Database-owned outer transaction commits, or executes it immediately.
     *
     * @param callable():void $callback
     */
    public function afterCommit(callable $callback): void
    {
        if ($this->managedTransactionDepth === 0) {
            $callback();

            return;
        }

        $this->afterCommitCallbacks[] = $callback;
    }

    /**
     * Retrieves the requested value without changing the current database state.
     */
    public function lastInsertId(): int|string|null
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function affectedRows(): int
    {
        return $this->connection->affectedRows();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function reconnect(): void
    {
        $this->connection->reconnect();
    }

    /**
     * Releases resources retained by this database object so they cannot be reused.
     */
    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function serverVersion(): string
    {
        return $this->connection->serverVersion();
    }

    /**
     * Escapes the supplied value or identifier for safe inclusion in driver-generated SQL.
     */
    public function escapeString(string $value): string
    {
        return $this->connection->escapeString($value);
    }

    /**
     * Determines whether a table is available in the current database schema.
     */
    public function tableExists(string $table): bool
    {
        return $this->driver->tableExists($table);
    }

    /**
     * Starts an immutable query builder scoped to a validated table identifier.
     */
    public function table(string $table): QueryBuilder
    {
        return QueryBuilder::make($this->driver)->table($table);
    }
}
