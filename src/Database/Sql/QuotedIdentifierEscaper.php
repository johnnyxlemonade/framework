<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Sql;

/**
 * Escapes identifier parts with a supplied pair of SQL quote characters.
 */
final readonly class QuotedIdentifierEscaper implements IdentifierEscaperInterface
{
    /**
     * Initializes this QuotedIdentifierEscaper with the collaborators and state required by its database contract.
     */
    public function __construct(
        private string $prefix,
        private string $quote,
    ) {
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function identifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        return implode('.', array_map(
            fn(string $part): string => $this->quote($part),
            explode('.', $identifier),
        ));
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function table(string $table): string
    {
        if ($this->prefix !== '' && !str_starts_with($table, $this->prefix)) {
            $table = $this->prefix . $table;
        }

        return $this->identifier($table);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    private function quote(string $part): string
    {
        return $this->quote
            . str_replace($this->quote, $this->quote . $this->quote, $part)
            . $this->quote;
    }
}
