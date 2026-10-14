<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

/**
 * Provides the table definition contract for the database subsystem.
 */
final readonly class TableDefinition
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
        private string $name,
        private array $columns = [],
        private array $modifiedColumns = [],
        private array $droppedColumns = [],
        private array $indexes = [],
        private array $droppedIndexes = [],
        private array $foreignKeys = [],
        private array $droppedForeignKeys = [],
        private bool $ifNotExists = false,
        private ?TableOptions $options = null,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }
    }

    /**
     * Builds the requested database value while preserving its framework contract.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Updates the query or object state used by subsequent database operations.
     */
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function ifNotExists(): bool
    {
        return $this->ifNotExists;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function options(): ?TableOptions
    {
        return $this->options;
    }

    /**
     * Reports whether the requested condition holds for the current database state.
     */
    public function hasCreateDefinitions(): bool
    {
        return $this->columns !== [] || $this->indexes !== [] || $this->foreignKeys !== [];
    }

    /**
     * Reports whether the requested condition holds for the current database state.
     */
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
