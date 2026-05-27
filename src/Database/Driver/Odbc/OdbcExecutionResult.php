<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

final class OdbcExecutionResult
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param list<OdbcField> $fields
     */
    public function __construct(
        private readonly bool $hasResultSet,
        private readonly array $rows,
        private readonly array $fields,
        private readonly int $affectedRows,
        private readonly int|string|null $insertId,
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
