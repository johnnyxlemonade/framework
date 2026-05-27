<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Connection;

interface ConnectionInterface
{
    /**
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array;

    /**
     * @param array<int|string, mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array $bindings = []): \Generator;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    /**
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;

    public function lastInsertId(): int|string|null;

    public function affectedRows(): int;

    public function reconnect(): void;

    public function close(): void;

    public function serverVersion(): string;

    public function escapeString(string $value): string;
}
