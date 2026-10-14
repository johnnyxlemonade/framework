<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\Schema\Definition\SqlExpression;

/**
 * Escapes ODBC string literals and LIKE patterns using the active ODBC connection.
 */
final readonly class OdbcSqlEscaper
{
    /**
     * Initializes this OdbcSqlEscaper with the collaborators and state required by its database contract.
     */
    public function __construct(
        private OdbcIdentifierEscaper $identifierEscaper,
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
