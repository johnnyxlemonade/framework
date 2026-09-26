<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

final readonly class OdbcExecutionResult
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param list<OdbcField> $fields
     */
    public function __construct(
        private bool $hasResultSet,
        private array $rows,
        private array $fields,
        private int $affectedRows,
        private int|string|null $insertId,
    ) {}

    public function hasResultSet(): bool
    {
        return $this->hasResultSet;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * @return list<OdbcField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function insertId(): int|string|null
    {
        return $this->insertId;
    }
}
