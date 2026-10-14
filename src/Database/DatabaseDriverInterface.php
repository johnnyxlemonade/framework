<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

/**
 * Defines the database driver operations used by the framework database layer.
 */
interface DatabaseDriverInterface
{
    /**
     * Determines whether a table is available in the current database schema.
     */
    public function tableExists(string $table): bool;

    /**
     * Executes SQL through the active driver and returns a cursor only for result-producing statements.
     *
     * @param array<int|string, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool;

    /**
     * Streams result rows without requiring the caller to work with a driver-specific cursor.
     *
     * @param array<int|string, mixed>|false $binds
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array|false $binds = false): \Generator;

    /**
     * Executes a statement without binding values or returning a result cursor.
     */
    public function simple_query(string $sql): bool;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function affected_rows(): int;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function insert_id(): int|string|null;

    /**
     * Escapes the supplied value or identifier for safe inclusion in driver-generated SQL.
     */
    public function escape(mixed $value): string;

    /**
     * Escapes the supplied value or identifier for safe inclusion in driver-generated SQL.
     */
    public function escape_str(string $value, bool $like = false): string;

    /**
     * Escapes the supplied value or identifier for safe inclusion in driver-generated SQL.
     */
    public function escape_like_str(string $value): string;

    /**
     * Escapes the supplied value or identifier for safe inclusion in driver-generated SQL.
     */
    public function escape_identifiers(string $item): string;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function platform(): string;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function version(): string;
}
