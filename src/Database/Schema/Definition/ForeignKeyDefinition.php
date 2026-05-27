<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Definition;

use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;

final class ForeignKeyDefinition
{
    /**
     * @param non-empty-list<string> $columns
     * @param non-empty-list<string> $referencedColumns
     */
    public function __construct(
        private readonly array $columns,
        private readonly string $referencedTable,
        private readonly array $referencedColumns = ['id'],
        private readonly ?string $name = null,
        private readonly ?ForeignKeyAction $onUpdate = null,
        private readonly ?ForeignKeyAction $onDelete = null,
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

    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate(ForeignKeyAction::Cascade);
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete(ForeignKeyAction::Cascade);
    }

    public function restrictOnUpdate(): self
    {
        return $this->onUpdate(ForeignKeyAction::Restrict);
    }

    public function restrictOnDelete(): self
    {
        return $this->onDelete(ForeignKeyAction::Restrict);
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete(ForeignKeyAction::SetNull);
    }

    public function onUpdate(ForeignKeyAction $action): self
    {
        return new self($this->columns, $this->referencedTable, $this->referencedColumns, $this->name, $action, $this->onDelete);
    }

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

    public function name(): ?string
    {
        return $this->name;
    }

    public function resolvedName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return 'fk_' . implode('_', $this->columns) . '_' . $this->referencedTable;
    }

    public function updateAction(): ?ForeignKeyAction
    {
        return $this->onUpdate;
    }

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
