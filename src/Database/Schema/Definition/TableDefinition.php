<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

final class TableDefinition
{
    /**
     * @param list<ColumnDefinition> $columns
     * @param list<ColumnDefinition> $modifiedColumns
     * @param list<string> $droppedColumns
     * @param list<IndexDefinition> $indexes
     * @param list<string> $droppedIndexes
     * @param list<ForeignKeyDefinition> $foreignKeys
     * @param list<string> $droppedForeignKeys
     */
    public function __construct(
        private readonly string $name,
        private readonly array $columns = [],
        private readonly array $modifiedColumns = [],
        private readonly array $droppedColumns = [],
        private readonly array $indexes = [],
        private readonly array $droppedIndexes = [],
        private readonly array $foreignKeys = [],
        private readonly array $droppedForeignKeys = [],
        private readonly bool $ifNotExists = false,
        private readonly ?TableOptions $options = null,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }
    }

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function withIfNotExists(bool $ifNotExists = true): self
    {
        return new self(
            $this->name,
            $this->columns,
            $this->modifiedColumns,
            $this->droppedColumns,
            $this->indexes,
            $this->droppedIndexes,
            $this->foreignKeys,
            $this->droppedForeignKeys,
            $ifNotExists,
            $this->options,
        );
    }

    public function withOptions(?TableOptions $options): self
    {
        return new self(
            $this->name,
            $this->columns,
            $this->modifiedColumns,
            $this->droppedColumns,
            $this->indexes,
            $this->droppedIndexes,
            $this->foreignKeys,
            $this->droppedForeignKeys,
            $this->ifNotExists,
            $options,
        );
    }

    public function withColumn(ColumnDefinition $column): self
    {
        return new self(
            $this->name,
            [...$this->columns, $column],
            $this->modifiedColumns,
            $this->droppedColumns,
            $this->indexes,
            $this->droppedIndexes,
            $this->foreignKeys,
            $this->droppedForeignKeys,
            $this->ifNotExists,
            $this->options,
        );
    }

    public function withModifiedColumn(ColumnDefinition $column): self
    {
        return new self(
            $this->name,
            $this->columns,
            [...$this->modifiedColumns, $column],
            $this->droppedColumns,
            $this->indexes,
            $this->droppedIndexes,
            $this->foreignKeys,
            $this->droppedForeignKeys,
            $this->ifNotExists,
            $this->options,
        );
    }

    public function withoutColumn(string $column): self
    {
        return new self(
            $this->name,
            $this->columns,
            $this->modifiedColumns,
            [...$this->droppedColumns, $column],
            $this->indexes,
            $this->droppedIndexes,
            $this->foreignKeys,
            $this->droppedForeignKeys,
            $this->ifNotExists,
            $this->options,
        );
    }

    public function withIndex(IndexDefinition $index): self
    {
        return new self(
            $this->name,
            $this->columns,
            $this->modifiedColumns,
            $this->droppedColumns,
            [...$this->indexes, $index],
            $this->droppedIndexes,
            $this->foreignKeys,
            $this->droppedForeignKeys,
            $this->ifNotExists,
            $this->options,
        );
    }

    public function withoutIndex(string $index): self
    {
        return new self(
            $this->name,
            $this->columns,
            $this->modifiedColumns,
            $this->droppedColumns,
            $this->indexes,
            [...$this->droppedIndexes, $index],
            $this->foreignKeys,
            $this->droppedForeignKeys,
            $this->ifNotExists,
            $this->options,
        );
    }

    public function withForeignKey(ForeignKeyDefinition $foreignKey): self
    {
        return new self(
            $this->name,
            $this->columns,
            $this->modifiedColumns,
            $this->droppedColumns,
            $this->indexes,
            $this->droppedIndexes,
            [...$this->foreignKeys, $foreignKey],
            $this->droppedForeignKeys,
            $this->ifNotExists,
            $this->options,
        );
    }

    public function withoutForeignKey(string $foreignKey): self
    {
        return new self(
            $this->name,
            $this->columns,
            $this->modifiedColumns,
            $this->droppedColumns,
            $this->indexes,
            $this->droppedIndexes,
            $this->foreignKeys,
            [...$this->droppedForeignKeys, $foreignKey],
            $this->ifNotExists,
            $this->options,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<ColumnDefinition> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<ColumnDefinition> */
    public function modifiedColumns(): array
    {
        return $this->modifiedColumns;
    }

    /** @return list<string> */
    public function droppedColumns(): array
    {
        return $this->droppedColumns;
    }

    /** @return list<IndexDefinition> */
    public function indexes(): array
    {
        return $this->indexes;
    }

    /** @return list<string> */
    public function droppedIndexes(): array
    {
        return $this->droppedIndexes;
    }

    /** @return list<ForeignKeyDefinition> */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    /** @return list<string> */
    public function droppedForeignKeys(): array
    {
        return $this->droppedForeignKeys;
    }

    public function ifNotExists(): bool
    {
        return $this->ifNotExists;
    }

    public function options(): ?TableOptions
    {
        return $this->options;
    }

    public function hasCreateDefinitions(): bool
    {
        return $this->columns !== [] || $this->indexes !== [] || $this->foreignKeys !== [];
    }

    public function hasAlterDefinitions(): bool
    {
        return $this->columns !== []
            || $this->modifiedColumns !== []
            || $this->droppedColumns !== []
            || $this->indexes !== []
            || $this->droppedIndexes !== []
            || $this->foreignKeys !== []
            || $this->droppedForeignKeys !== [];
    }
}
