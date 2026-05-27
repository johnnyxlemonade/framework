<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database;

interface DatabaseResultInterface
{
    public function num_rows(): int;

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

    public function row(int|string $n = 0, string $type = 'object'): mixed;

    /**
     * @return array<string, mixed>|null
     */
    public function row_array(int $n = 0): ?array;

    public function row_object(int $n = 0): ?object;

    public function first_row(string $type = 'object'): mixed;

    public function last_row(string $type = 'object'): mixed;

    public function next_row(string $type = 'object'): mixed;

    public function previous_row(string $type = 'object'): mixed;

    public function data_seek(int $n = 0): bool;

    public function free_result(): void;
}
