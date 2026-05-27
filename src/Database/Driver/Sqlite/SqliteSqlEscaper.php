<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Sqlite;

use Lemonade\Framework\Database\Schema\Definition\SqlExpression;

final class SqliteSqlEscaper
{
    public function __construct(
        private readonly SqliteIdentifierEscaper $identifierEscaper,
    ) {}

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

    public function identifier(string $identifier): string
    {
        return $this->identifierEscaper->identifier($identifier);
    }

    public function table(string $table): string
    {
        return $this->identifierEscaper->table($table);
    }
}
