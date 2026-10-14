<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\Result\ArrayDatabaseResult;

/**
 * Provides the odbc result contract for the database subsystem.
 */
final class OdbcResult extends ArrayDatabaseResult
{
    /**
     * @var list<OdbcField>
     */
    private array $fields;

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<OdbcField> $fields
     */
    private function __construct(array $rows, array $fields)
    {
        parent::__construct($rows);
        $this->fields = $fields;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<OdbcField> $fields
     */
    public static function fromRows(array $rows, array $fields): self
    {
        return new self($rows, $fields);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function num_fields(): int
    {
        return count($this->fields);
    }

    /**
     * @return list<string>
     */
    public function list_fields(): array
    {
        return array_map(
            static fn(OdbcField $field): string => $field->name(),
            $this->fields,
        );
    }

    /**
     * @return list<object>
     */
    public function field_data(): array
    {
        return array_map(
            static fn(OdbcField $field): object => $field->toObject(),
            $this->fields,
        );
    }

    /**
     * Releases resources retained by this database object so they cannot be reused.
     */
    public function free_result(): void
    {
        parent::free_result();
        $this->fields = [];
    }
}
