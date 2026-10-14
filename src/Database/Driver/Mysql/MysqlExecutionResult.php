<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use mysqli_result;

/**
 * Captures raw MySQL execution output together with write metadata needed by the driver facade.
 */
final readonly class MysqlExecutionResult
{
    /**
     * Captures a result-set handle or successful write marker with its affected rows and generated identifier.
     */
    public function __construct(
        private mysqli_result|bool $result,
        private int $affectedRows,
        private int|string $insertId,
    ) {
    }

    /**
     * Returns the raw mysqli execution result retained for result cursor construction.
     */
    public function result(): mysqli_result|bool
    {
        return $this->result;
    }

    /**
     * Indicates whether execution produced a mysqli result set rather than a write acknowledgement.
     */
    public function hasResultSet(): bool
    {
        return $this->result instanceof mysqli_result;
    }

    /**
     * Returns the mysqli result handle only when execution produced rows.
     */
    public function mysqliResult(): ?mysqli_result
    {
        return $this->result instanceof mysqli_result ? $this->result : null;
    }

    /**
     * Returns the affected-row count captured immediately after execution.
     */
    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * Returns the generated identifier captured immediately after execution.
     */
    public function insertId(): int|string
    {
        return $this->insertId;
    }
}
