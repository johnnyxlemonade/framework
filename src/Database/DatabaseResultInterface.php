<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

/**
 * Defines the legacy-compatible result cursor and projection operations exposed by database drivers.
 */
interface DatabaseResultInterface
{
    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function num_rows(): int;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function num_fields(): int;

    /**
     * @return list<string>
     */
    public function list_fields(): array;

    /**
     * @return list<object>
     */
    public function field_data(): array;

    /**
     * @return list<object>|list<array<string, mixed>>
     */
    public function result(string $type = 'object'): array;

    /**
     * @return list<object>
     */
    public function result_object(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function result_array(): array;

    /**
     * @return list<object>
     */
    public function custom_result_object(string $className): array;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function row(int|string $n = 0, string $type = 'object'): mixed;

    /**
     * @return array<string, mixed>|null
     */
    public function row_array(int $n = 0): ?array;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function row_object(int $n = 0): ?object;

    /**
     * Retrieves the requested value without changing the current database state.
     */
    public function first_row(string $type = 'object'): mixed;

    /**
     * Retrieves the requested value without changing the current database state.
     */
    public function last_row(string $type = 'object'): mixed;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function next_row(string $type = 'object'): mixed;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function previous_row(string $type = 'object'): mixed;

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function data_seek(int $n = 0): bool;

    /**
     * Releases resources retained by this database object so they cannot be reused.
     */
    public function free_result(): void;
}
