<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

interface DatabaseDriverInterface
{
    /**
     * @param array<int|string, mixed>|false $binds
     */
    public function query(string $sql, array|false $binds = false): DatabaseResultInterface|bool;

    /**
     * @param array<int|string, mixed>|false $binds
     * @return \Generator<int, array<string, mixed>, void, void>
     */
    public function cursor(string $sql, array|false $binds = false): \Generator;

    public function simple_query(string $sql): bool;

    public function affected_rows(): int;

    public function insert_id(): int|string|null;

    public function escape(mixed $value): string;

    public function escape_str(string $value, bool $like = false): string;

    public function escape_like_str(string $value): string;

    public function escape_identifiers(string $item): string;

    public function protect_identifiers(
        string $item,
        bool $prefixSingle = false,
        ?bool $protectIdentifiers = null,
        bool $fieldExists = true,
    ): string;

    public function platform(): string;

    public function version(): string;
}
