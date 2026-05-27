<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Database\Schema\Definition\SqlExpression;

final class MysqlSqlEscaper
{
    public function __construct(
        private readonly MysqlIdentifierEscaper $identifierEscaper,
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

        return "'" . str_replace(
            ['\\', "'", "\0", "\n", "\r", "\x1a"],
            ['\\\\', "\\'", '\\0', '\\n', '\\r', '\\Z'],
            (string) $value,
        ) . "'";
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
