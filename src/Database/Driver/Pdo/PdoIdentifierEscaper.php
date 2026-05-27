<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Pdo;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Connection\DatabaseDialect;

final class PdoIdentifierEscaper
{
    private function __construct(
        private readonly string $prefix,
        private readonly string $quote,
    ) {}

    public static function fromConfig(DatabaseConfig $config): self
    {
        $quote = match ($config->dialect()) {
            DatabaseDialect::Sqlite => '"',
            default => '`',
        };

        return new self(
            prefix: $config->prefix(),
            quote: $quote,
        );
    }

    public function identifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        $parts = explode('.', $identifier);

        return implode('.', array_map(
            fn(string $part): string => $this->quote($part),
            $parts,
        ));
    }

    public function table(string $table): string
    {
        if ($this->prefix !== '' && !str_starts_with($table, $this->prefix)) {
            $table = $this->prefix . $table;
        }

        return $this->identifier($table);
    }

    private function quote(string $identifierPart): string
    {
        return $this->quote
            . str_replace($this->quote, $this->quote . $this->quote, $identifierPart)
            . $this->quote;
    }
}
