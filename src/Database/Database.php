<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

use Lemonade\Framework\Database\Connection\ConnectionInterface;

final class Database
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DatabaseDriverInterface $driver,
    ) {}

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

    public function transaction(callable $callback): mixed
    {
        return $this->connection->transaction($callback);
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
