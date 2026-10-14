<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\Sql\IdentifierEscaperInterface;
use Lemonade\Framework\Database\Sql\QuotedIdentifierEscaper;

/**
 * Provides the odbc identifier escaper contract for the database subsystem.
 */
final readonly class OdbcIdentifierEscaper implements IdentifierEscaperInterface
{
    private QuotedIdentifierEscaper $escaper;

    /**
     * Initializes this OdbcIdentifierEscaper with the collaborators and state required by its database contract.
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
