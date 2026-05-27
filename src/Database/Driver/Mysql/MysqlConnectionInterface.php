<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use mysqli;

interface MysqlConnectionInterface
{
    public function mysqli(): mysqli;

    /**
     * @param array<int|string, mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): MysqlExecutionResult;
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

    public function reconnect(): void;

    public function close(): void;

    public function serverVersion(): string;

    public function setCharset(string $charset): bool;

    public function escapeString(string $value): string;

    public function selectDatabase(string $database): void;

    public function affectedRows(): int;

    public function lastInsertId(): int|string|null;

    /**
     * @return list<string>
     */
    public function listTables(bool $prefixLimit = false): array;

    /**
     * @return list<string>
     */
    public function listColumns(string $table): array;

    /**
     * @return array{code:int|string|null,message:string|null}
     */
    public function lastError(): array;
}
