<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Sqlite;

use Lemonade\Framework\Database\Sql\IdentifierEscaperInterface;
use Lemonade\Framework\Database\Sql\QuotedIdentifierEscaper;

/**
 * Escapes SQLite identifier parts with SQLite-compatible quoting rules.
 */
final readonly class SqliteIdentifierEscaper implements IdentifierEscaperInterface
{
    private QuotedIdentifierEscaper $escaper;

    /**
     * Initializes this SqliteIdentifierEscaper with the collaborators and state required by its database contract.
     */
    public function __construct(string $prefix)
    {
        $this->escaper = new QuotedIdentifierEscaper($prefix, '"');
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function identifier(string $identifier): string
    {
        return $this->escaper->identifier($identifier);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function table(string $table): string
    {
        return $this->escaper->table($table);
    }
}
