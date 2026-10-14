<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

use Lemonade\Framework\Database\Sql\IdentifierEscaperInterface;
use Lemonade\Framework\Database\Sql\QuotedIdentifierEscaper;

/**
 * Applies MySQL backtick quoting and the configured prefix to SQL identifiers.
 */
final readonly class MysqlIdentifierEscaper implements IdentifierEscaperInterface
{
    private QuotedIdentifierEscaper $escaper;

    /**
     * Creates an escaper whose prefix is applied by the shared quoted-identifier implementation.
     */
    public function __construct(string $prefix)
    {
        $this->escaper = new QuotedIdentifierEscaper($prefix, '`');
    }

    /**
     * Escapes one MySQL identifier while preserving the identifier contract shared by database drivers.
     */
    public function identifier(string $identifier): string
    {
        return $this->escaper->identifier($identifier);
    }

    /**
     * Escapes a table reference and applies the configured prefix once.
     */
    public function table(string $table): string
    {
        return $this->escaper->table($table);
    }
}
