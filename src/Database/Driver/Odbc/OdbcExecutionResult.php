<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

/**
 * Captures the statement resource and affected row count produced by an ODBC execution.
 */
final readonly class OdbcExecutionResult
{
    /**
     * Captures materialized ODBC result metadata so later result projections do not depend on a live statement cursor.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<OdbcField> $fields
     */
    public function __construct(
        private bool $hasResultSet,
        private array $rows,
        private array $fields,
        private int $affectedRows,
        private int|string|null $insertId,
    ) {
    }

    /**
     * Indicates whether the executed statement produced a result set.
     */
    public function hasResultSet(): bool
    {
        return $this->hasResultSet;
    }

    /**
     * Returns materialized associative result rows in driver read order.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    /**
     * Returns column metadata corresponding to the materialized rows.
     *
     * @return list<OdbcField>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * Returns the affected-row count captured immediately after execution.
     */
    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * Returns the generated identifier when the ODBC driver could provide one.
     */
    public function insertId(): int|string|null
    {
        return $this->insertId;
    }
}
