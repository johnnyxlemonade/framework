<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\Result\ArrayDatabaseResult;

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

    public function free_result(): void
    {
        parent::free_result();
        $this->fields = [];
    }
}
