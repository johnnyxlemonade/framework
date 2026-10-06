<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Database\Connection\ConnectionInterface;

final class Database
{
    /** @var list<callable():void> */
    private array $afterCommitCallbacks = [];

    private int $managedTransactionDepth = 0;
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DatabaseDriverInterface $driver,
    ) {
    }

    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->connection->select($sql, $bindings);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        return $this->connection->statement($sql, $bindings);
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array $bindings = []): \Generator
    {
        return $this->connection->cursor($sql, $bindings);
    }

    /**
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
     */
    public function afterCommit(callable $callback): void
    {
        if ($this->managedTransactionDepth === 0) {
            $callback();

            return;
        }

        $this->afterCommitCallbacks[] = $callback;
    }

    public function lastInsertId(): int|string|null
    {
        return $this->connection->lastInsertId();
    }

    public function affectedRows(): int
    {
        return $this->connection->affectedRows();
    }

    public function reconnect(): void
    {
        $this->connection->reconnect();
    }

    public function close(): void
    {
        $this->connection->close();
    }

    public function serverVersion(): string
    {
        return $this->connection->serverVersion();
    }

    public function escapeString(string $value): string
    {
        return $this->connection->escapeString($value);
    }

    public function table(string $table): QueryBuilder
    {
        return QueryBuilder::make($this->driver)->table($table);
    }
}
