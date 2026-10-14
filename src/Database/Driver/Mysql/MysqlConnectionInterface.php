<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use mysqli;

/**
 * Defines MySQLi-specific operations used by the MySQL driver in addition to the generic connection contract.
 */
interface MysqlConnectionInterface
{
    /**
     * Returns the underlying MySQLi handle for extension-specific operations.
     */
    public function mysqli(): mysqli;

    /**
     * Executes SQL with bound values and captures both result-set and write metadata.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): MysqlExecutionResult;
    /**
     * Reads an entire result set as associative rows.
     *
     * @param array<int|string, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array;
    /**
     * Streams associative rows without materializing the result set.
     *
     * @param array<int|string, mixed> $bindings
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array $bindings = []): \Generator;
    /**
     * Executes a data-changing statement and returns its affected-row count.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int;

    /**
     * Begins a physical MySQL transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commits the active physical MySQL transaction.
     */
    public function commit(): void;

    /**
     * Rolls back the active physical MySQL transaction.
     */
    public function rollBack(): void;

    /**
     * Reconnects the underlying MySQLi handle using its configured connection settings.
     */
    public function reconnect(): void;

    /**
     * Closes the underlying MySQLi handle.
     */
    public function close(): void;

    /**
     * Returns the MySQL server version reported by MySQLi.
     */
    public function serverVersion(): string;

    /**
     * Requests the connection character set and reports whether MySQL accepted it.
     */
    public function setCharset(string $charset): bool;

    /**
     * Escapes a string fragment for inclusion in a MySQL literal.
     */
    public function escapeString(string $value): string;

    /**
     * Selects the active MySQL database for subsequent statements.
     */
    public function selectDatabase(string $database): void;

    /**
     * Returns the affected-row count reported by the latest MySQLi operation.
     */
    public function affectedRows(): int;

    /**
     * Returns the generated identifier reported by the latest insert when available.
     */
    public function lastInsertId(): int|string|null;

    /**
     * Lists database tables, optionally restricted to the configured table prefix.
     *
     * @return list<string>
     */
    public function listTables(bool $prefixLimit = false): array;

    /**
     * Lists column names for one table through MySQL metadata.
     *
     * @return list<string>
     */
    public function listColumns(string $table): array;

    /**
     * Returns the code and message reported by the latest MySQLi error.
     *
     * @return array{code:int|string|null,message:string|null}
     */
    public function lastError(): array;
}
