<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Database\DatabaseResult;
use mysqli_result;

/**
 * Adapts a MySQLi result handle to cached framework result projections.
 */
final class MysqlResult extends DatabaseResult
{
    /**
     * Retains the MySQLi result handle; public callers construct instances through fromMysqliResult().
     */
    private function __construct(
        private readonly mysqli_result $result,
    ) {
    }

    /**
     * Wraps a result-producing MySQLi execution for framework result consumption.
     */
    public static function fromMysqliResult(mysqli_result $result): self
    {
        return new self($result);
    }

    /**
     * Returns the number of rows buffered by the MySQLi result handle.
     */
    public function num_rows(): int
    {
        return (int) $this->result->num_rows;
    }

    /**
     * Returns the number of fields described by the MySQLi result handle.
     */
    public function num_fields(): int
    {
        return $this->result->field_count;
    }

    /**
     * Lists field names in the result-set order reported by MySQLi.
     *
     * @return list<string>
     */
    public function list_fields(): array
    {
        $fields = [];

        $this->result->field_seek(0);

        while (($field = $this->result->fetch_field()) !== false) {
            $fields[] = $field->name;
        }

        return $fields;
    }

    /**
     * Converts MySQLi field metadata into framework field value objects.
     *
     * @return list<MysqlField>
     */
    public function field_data(): array
    {
        $fields = [];

        foreach ($this->result->fetch_fields() as $fieldData) {
            $fields[] = new MysqlField(
                name: $fieldData->name,
                type: $this->getFieldType($fieldData->type),
                maxLength: $fieldData->max_length,
                primaryKey: ($fieldData->flags & MYSQLI_PRI_KEY_FLAG) !== 0,
                default: $fieldData->def,
            );
        }

        return $fields;
    }

    /**
     * Converts field metadata to the legacy object shape expected by compatible consumers.
     *
     * @return list<object>
     */
    public function field_data_object(): array
    {
        return array_map(
            static fn(MysqlField $field): object => $field->toObject(),
            $this->field_data(),
        );
    }

    /**
     * Repositions the MySQLi result cursor for subsequent row reads.
     */
    public function data_seek(int $n = 0): bool
    {
        return $this->result->data_seek($n);
    }

    /**
     * Releases the native MySQLi result buffer.
     */
    public function free_result(): void
    {
        $this->result->free();
    }

    /**
     * Fetches the next native row as an associative array for DatabaseResult traversal.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchAssoc(): ?array
    {
        $row = $this->result->fetch_assoc();

        return is_array($row) ? $row : null;
    }

    /**
     * Maps MySQLi field constants to stable legacy type names while preserving unknown constants.
     */
    private function getFieldType(int $type): string|int
    {
        return match ($type) {
            MYSQLI_TYPE_DECIMAL => 'decimal',
            MYSQLI_TYPE_BIT => 'bit',
            MYSQLI_TYPE_TINY => 'tinyint',
            MYSQLI_TYPE_SHORT => 'smallint',
            MYSQLI_TYPE_INT24 => 'mediumint',
            MYSQLI_TYPE_LONG => 'int',
            MYSQLI_TYPE_LONGLONG => 'bigint',
            MYSQLI_TYPE_FLOAT => 'float',
            MYSQLI_TYPE_DOUBLE => 'double',
            MYSQLI_TYPE_TIMESTAMP => 'timestamp',
            MYSQLI_TYPE_DATE => 'date',
            MYSQLI_TYPE_TIME => 'time',
            MYSQLI_TYPE_DATETIME => 'datetime',
            MYSQLI_TYPE_YEAR => 'year',
            MYSQLI_TYPE_NEWDATE => 'date',
            MYSQLI_TYPE_ENUM => 'enum',
            MYSQLI_TYPE_SET => 'set',
            MYSQLI_TYPE_TINY_BLOB => 'tinyblob',
            MYSQLI_TYPE_MEDIUM_BLOB => 'mediumblob',
            MYSQLI_TYPE_BLOB => 'blob',
            MYSQLI_TYPE_LONG_BLOB => 'longblob',
            MYSQLI_TYPE_STRING => 'char',
            MYSQLI_TYPE_VAR_STRING => 'varchar',
            MYSQLI_TYPE_GEOMETRY => 'geometry',
            default => $type,
        };
    }
}
