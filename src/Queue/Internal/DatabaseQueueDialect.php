<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Internal;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\DatabaseDialect;
use Lemonade\Framework\Database\Connection\Driver;

/**
 * Supplies the trusted database-time expression for queue dialects with reliable conditional write counts.
 *
 * @internal
 */
final readonly class DatabaseQueueDialect
{
    private function __construct(
        private DatabaseDialect $dialect,
        private string $currentEpochExpression,
    ) {
    }

    /**
     * Resolves a supported queue dialect and rejects generic ODBC before any job can be mutated.
     */
    public static function fromConfig(DatabaseConfig $config): self
    {
        if ($config->driver() === Driver::Odbc) {
            throw new \LogicException(
                'Database queue transport does not support ODBC because its database-time and affected-row contracts are not safely verified.',
            );
        }

        return match ($config->dialect()) {
            DatabaseDialect::Mysql => new self(
                DatabaseDialect::Mysql,
                'UNIX_TIMESTAMP(CURRENT_TIMESTAMP)',
            ),
            DatabaseDialect::Sqlite => new self(
                DatabaseDialect::Sqlite,
                "CAST(strftime('%s', 'now') AS INTEGER)",
            ),
            default => throw new \LogicException(
                'Database queue transport requires a supported MySQL or SQLite database dialect.',
            ),
        };
    }

    /**
     * Returns the dialect-owned SQL expression used as the authoritative Unix time source.
     */
    public function currentEpochExpression(): string
    {
        return $this->currentEpochExpression;
    }

    /**
     * Indicates whether schema inspection must use SQLite's table-info metadata instead of column probing.
     */
    public function isSqlite(): bool
    {
        return $this->dialect === DatabaseDialect::Sqlite;
    }
}
