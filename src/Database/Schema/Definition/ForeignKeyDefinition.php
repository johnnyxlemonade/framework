<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;

/**
 * Provides the foreign key definition contract for the database subsystem.
 */
final readonly class ForeignKeyDefinition
{
    /**
     * @param non-empty-list<string> $columns
     * @param non-empty-list<string> $referencedColumns
     */
    public function __construct(
        private array $columns,
        private string $referencedTable,
        private array $referencedColumns = ['id'],
        private ?string $name = null,
        private ?ForeignKeyAction $onUpdate = null,
        private ?ForeignKeyAction $onDelete = null,
    ) {
        if (trim($referencedTable) === '') {
            throw new \InvalidArgumentException('Referenced table cannot be empty.');
        }
    }

    /**
     * @param string|non-empty-list<string> $columns
     * @param string|non-empty-list<string> $referencedColumns
     */
    public static function make(
        string|array $columns,
        string $referencedTable,
        string|array $referencedColumns = ['id'],
        ?string $name = null,
    ): self {
        return new self(
            self::normalizeColumns($columns),
            $referencedTable,
            self::normalizeColumns($referencedColumns),
            $name,
        );
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate(ForeignKeyAction::Cascade);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete(ForeignKeyAction::Cascade);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate(ForeignKeyAction::Restrict);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete(ForeignKeyAction::Restrict);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete(ForeignKeyAction::SetNull);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function onUpdate(ForeignKeyAction $action): self
    {
        return new self($this->columns, $this->referencedTable, $this->referencedColumns, $this->name, $action, $this->onDelete);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function onDelete(ForeignKeyAction $action): self
    {
        return new self($this->columns, $this->referencedTable, $this->referencedColumns, $this->name, $this->onUpdate, $action);
    }

    /**
     * @return non-empty-list<string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function referencedTable(): string
    {
        return $this->referencedTable;
    }

    /**
     * @return non-empty-list<string>
     */
    public function referencedColumns(): array
    {
        return $this->referencedColumns;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Resolves the effective value from the configured database dependencies and input.
     */
    public function resolvedName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return 'fk_' . implode('_', $this->columns) . '_' . $this->referencedTable;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function updateAction(): ?ForeignKeyAction
    {
        return $this->onUpdate;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function deleteAction(): ?ForeignKeyAction
    {
        return $this->onDelete;
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
            throw new \InvalidArgumentException('Column list cannot be empty.');
        }

        /** @var non-empty-list<non-empty-string> $normalized */
        return $normalized;
    }
}
