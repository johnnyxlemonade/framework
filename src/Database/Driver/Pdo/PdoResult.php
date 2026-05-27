<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Database\DatabaseResult;

final class PdoResult extends DatabaseResult
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $rows;

    private int $pointer = 0;

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function fromRows(array $rows): self
    {
        return new self($rows);
    }

    public function num_rows(): int
    {
        return count($this->rows);
    }

    public function num_fields(): int
    {
        if (!isset($this->rows[0])) {
            return 0;
        }

        return count($this->rows[0]);
    }

    /**
     * @return list<string>
     */
    public function list_fields(): array
    {
        if (!isset($this->rows[0])) {
            return [];
        }

        return array_keys($this->rows[0]);
    }

    /**
     * @return list<object>
     */
    public function field_data(): array
    {
        $fields = [];

        foreach ($this->list_fields() as $fieldName) {
            $fields[] = (object) [
                'name' => $fieldName,
                'type' => 'mixed',
                'max_length' => 0,
                'primary_key' => false,
                'default' => null,
            ];
        }

        return $fields;
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
