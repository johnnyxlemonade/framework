<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use mysqli_result;

final class MysqlExecutionResult
{
    public function __construct(
        private readonly mysqli_result|bool $result,
        private readonly int $affectedRows,
        private readonly int|string $insertId,
    ) {}

    public function result(): mysqli_result|bool
    {
        return $this->result;
    }

    public function hasResultSet(): bool
    {
        return $this->result instanceof mysqli_result;
    }

    public function mysqliResult(): ?mysqli_result
    {
        return $this->result instanceof mysqli_result ? $this->result : null;
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function insertId(): int|string
    {
        return $this->insertId;
    }
}
