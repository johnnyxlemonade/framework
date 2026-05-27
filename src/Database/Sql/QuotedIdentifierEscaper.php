<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Sql;

final class QuotedIdentifierEscaper implements IdentifierEscaperInterface
{
    public function __construct(
        private readonly string $prefix,
        private readonly string $quote,
    ) {}

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

    public function table(string $table): string
    {
        if ($this->prefix !== '' && !str_starts_with($table, $this->prefix)) {
            $table = $this->prefix . $table;
        }

        return $this->identifier($table);
    }

    private function quote(string $part): string
    {
        return $this->quote
            . str_replace($this->quote, $this->quote . $this->quote, $part)
            . $this->quote;
    }
}
