<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\DatabaseResult;

final class OdbcResult extends DatabaseResult
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $rows;

    /**
     * @var list<OdbcField>
     */
    private array $fields;

    private int $pointer = 0;

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<OdbcField> $fields
     */
    private function __construct(array $rows, array $fields)
    {
        $this->rows = $rows;
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

    public function num_rows(): int
    {
        return count($this->rows);
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

    public function data_seek(int $n = 0): bool
    {
        if ($n < 0 || $n >= count($this->rows)) {
            return false;
        }

        $this->pointer = $n;

        return true;
    }

    public function free_result(): void
    {
        $this->rows = [];
        $this->fields = [];
        $this->pointer = 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchAssoc(): ?array
    {
        if (!isset($this->rows[$this->pointer])) {
            return null;
        }

        return $this->rows[$this->pointer++];
    }
}
