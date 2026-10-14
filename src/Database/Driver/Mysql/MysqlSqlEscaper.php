<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Database\Schema\Definition\SqlExpression;

/**
 * Renders MySQL literals, expressions, and identifiers for schema compilation.
 */
final readonly class MysqlSqlEscaper
{
    /**
     * Creates an escaper using the configured MySQL identifier rules.
     */
    public function __construct(
        private MysqlIdentifierEscaper $identifierEscaper,
    ) {
    }

    /**
     * Converts a scalar, nullable value, or explicit SQL expression to a MySQL literal.
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

        return "'" . str_replace(
            ['\\', "'", "\0", "\n", "\r", "\x1a"],
            ['\\\\', "\\'", '\\0', '\\n', '\\r', '\\Z'],
            (string) $value,
        ) . "'";
    }

    /**
     * Escapes one identifier for use in generated MySQL DDL.
     */
    public function identifier(string $identifier): string
    {
        return $this->identifierEscaper->identifier($identifier);
    }

    /**
     * Escapes a table name and applies its configured prefix.
     */
    public function table(string $table): string
    {
        return $this->identifierEscaper->table($table);
    }
}
