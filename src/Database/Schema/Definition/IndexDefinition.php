<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

use Lemonade\Framework\Database\Schema\Enum\IndexType;

final class IndexDefinition
{
    /**
     * @param non-empty-list<string> $columns
     */
    public function __construct(
        private readonly IndexType $type,
        private readonly array $columns,
        private readonly ?string $name = null,
        private readonly bool $ifNotExists = false,
    ) {}

    /**
     * @param string|non-empty-list<string> $columns
     */
    public static function primary(string|array $columns, ?string $name = null, bool $ifNotExists = false): self
    {
        return new self(IndexType::Primary, self::normalizeColumns($columns), $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public static function index(string|array $columns, ?string $name = null, bool $ifNotExists = false): self
    {
        return new self(IndexType::Index, self::normalizeColumns($columns), $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public static function unique(string|array $columns, ?string $name = null, bool $ifNotExists = false): self
    {
        return new self(IndexType::Unique, self::normalizeColumns($columns), $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public static function fulltext(string|array $columns, ?string $name = null, bool $ifNotExists = false): self
    {
        return new self(IndexType::Fulltext, self::normalizeColumns($columns), $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public static function spatial(string|array $columns, ?string $name = null, bool $ifNotExists = false): self
    {
        return new self(IndexType::Spatial, self::normalizeColumns($columns), $name, $ifNotExists);
    }

    public function type(): IndexType
    {
        return $this->type;
    }

    /**
     * @return non-empty-list<string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function ifNotExists(): bool
    {
        return $this->ifNotExists;
    }

    public function withIfNotExists(bool $ifNotExists = true): self
    {
        return new self($this->type, $this->columns, $this->name, $ifNotExists);
    }

    public function resolvedName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return strtolower($this->type->value . '_' . implode('_', $this->columns));
    }

    /**
     * @param string|non-empty-list<string> $columns
     * @return non-empty-list<string>
     */
    private static function normalizeColumns(string|array $columns): array
    {
        $normalized = is_array($columns) ? $columns : [$columns];
        $normalized = array_values(array_filter(
            array_map(static fn(string $column): string => trim($column), $normalized),
            static fn(string $column): bool => $column !== '',
        ));

        if ($normalized === []) {
            throw new \InvalidArgumentException('Index must contain at least one column.');
        }

        /** @var non-empty-list<non-empty-string> $normalized */
        return $normalized;
    }
}
