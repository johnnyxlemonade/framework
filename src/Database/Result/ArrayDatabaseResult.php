<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Result;

use Lemonade\Framework\Database\DatabaseResult;

/**
 * Provides the array database result contract for the database subsystem.
 */
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function num_rows(): int
    {
        return count($this->rows);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function data_seek(int $n = 0): bool
    {
        if ($n < 0 || $n >= count($this->rows)) {
            return false;
        }

        $this->pointer = $n;

        return true;
    }

    /**
     * Releases resources retained by this database object so they cannot be reused.
     */
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
