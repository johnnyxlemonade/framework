<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Result;

use Lemonade\Framework\Database\DatabaseResult;

abstract class ArrayDatabaseResult extends DatabaseResult
{
    /**
     * @var list<array<string, mixed>>
     */
    protected array $rows;

    protected int $pointer = 0;

    /**
     * @param list<array<string, mixed>> $rows
     */
    protected function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function num_rows(): int
    {
        return count($this->rows);
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
