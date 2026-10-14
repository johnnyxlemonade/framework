<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Sqlite;

use Lemonade\Framework\Database\Schema\Definition\SqlExpression;

/**
 * Escapes SQLite string literals and LIKE patterns through the PDO connection.
 */
final readonly class SqliteSqlEscaper
{
    /**
     * Initializes this SqliteSqlEscaper with the collaborators and state required by its database contract.
     */
    public function __construct(
        private SqliteIdentifierEscaper $identifierEscaper,
    ) {
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function value(mixed $value): string
    {
        if ($value instanceof SqlExpression) {
            return $value->sql();
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (!$value instanceof \Stringable && !is_scalar($value)) {
            return "''";
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function identifier(string $identifier): string
    {
        return $this->identifierEscaper->identifier($identifier);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function table(string $table): string
    {
        return $this->identifierEscaper->table($table);
    }
}
