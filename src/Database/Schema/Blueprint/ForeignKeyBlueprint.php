<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Blueprint;

use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;

final class ForeignKeyBlueprint
{
    /**
     * @param non-empty-list<string> $columns
     */
    public function __construct(
        private readonly array $columns,
        private ?string $referencedTable = null,
        /** @var non-empty-list<string> */
        private array $referencedColumns = ['id'],
        private ?string $name = null,
        private ?ForeignKeyAction $onUpdate = null,
        private ?ForeignKeyAction $onDelete = null,
    ) {}

    /**
     * @param string|non-empty-list<string> $columns
     */
    public function references(string|array $columns): self
    {
        $this->referencedColumns = self::normalizeColumns($columns);

        return $this;
    }

    public function on(string $table): self
    {
        $this->referencedTable = $table;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function onUpdate(ForeignKeyAction $action): self
    {
        $this->onUpdate = $action;

        return $this;
    }

    public function onDelete(ForeignKeyAction $action): self
    {
        $this->onDelete = $action;

        return $this;
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

    public function definition(): ForeignKeyDefinition
    {
        if ($this->referencedTable === null || trim($this->referencedTable) === '') {
            throw new \LogicException('Foreign key referenced table is not defined. Call on().');
        }

        return new ForeignKeyDefinition(
            columns: $this->columns,
            referencedTable: $this->referencedTable,
            referencedColumns: $this->referencedColumns,
            name: $this->name,
            onUpdate: $this->onUpdate,
            onDelete: $this->onDelete,
        );
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
