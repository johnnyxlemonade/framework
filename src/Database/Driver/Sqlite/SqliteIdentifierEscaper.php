<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Sqlite;

use Lemonade\Framework\Database\Sql\IdentifierEscaperInterface;
use Lemonade\Framework\Database\Sql\QuotedIdentifierEscaper;

final class SqliteIdentifierEscaper implements IdentifierEscaperInterface
{
    private readonly QuotedIdentifierEscaper $escaper;

    public function __construct(string $prefix)
    {
        $this->escaper = new QuotedIdentifierEscaper($prefix, '"');
    }

    public function identifier(string $identifier): string
    {
        return $this->escaper->identifier($identifier);
    }

    public function table(string $table): string
    {
        return $this->escaper->table($table);
    }
}
