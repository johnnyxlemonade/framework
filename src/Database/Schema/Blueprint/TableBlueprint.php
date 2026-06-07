<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Blueprint;

use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableOptions;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;

final class TableBlueprint
{
    /** @var list<ColumnBlueprint> */
    private array $columns = [];

    /** @var list<ColumnBlueprint> */
    private array $modifiedColumns = [];

    /** @var list<string> */
    private array $droppedColumns = [];

    /** @var list<IndexDefinition> */
    private array $indexes = [];

    /** @var list<string> */
    private array $droppedIndexes = [];

    /** @var list<ForeignKeyBlueprint> */
    private array $foreignKeys = [];

    /** @var list<string> */
    private array $droppedForeignKeys = [];

    private ?TableOptions $options = null;

    public function __construct(
        private readonly string $name,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }
    }

    public function id(string $name = 'id'): ColumnBlueprint
    {
        $column = $this->bigInteger($name)
            ->unsigned()
            ->autoIncrement();

        $this->primary($name);

        return $column;
    }

    public function uuid(string $name = 'uuid'): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Uuid, 36);
    }

    public function char(string $name, int $length = 255): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Char, $length);
    }

    public function string(string $name, int $length = 255): ColumnBlueprint
    {
        return $this->column($name, ColumnType::String, $length);
    }

    public function text(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Text);
    }

    public function mediumText(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::MediumText);
    }

    public function longText(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::LongText);
    }

    public function integer(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Integer);
    }

    public function unsignedInteger(string $name): ColumnBlueprint
    {
        return $this->integer($name)->unsigned();
    }

    public function tinyInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::TinyInteger);
    }

    public function smallInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::SmallInteger);
    }

    public function mediumInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::MediumInteger);
    }

    public function bigInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::BigInteger);
    }

    public function unsignedBigInteger(string $name): ColumnBlueprint
    {
        return $this->bigInteger($name)->unsigned();
    }

    public function boolean(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Boolean, 1);
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Decimal, $precision . ',' . $scale);
    }

    public function float(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Float);
    }

    public function double(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Double);
    }

    public function date(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Date);
    }

    public function datetime(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::DateTime);
    }

    public function timestamp(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Timestamp);
    }

    public function time(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Time);
    }

    public function json(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Json);
    }

    public function binary(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Binary);
    }

    public function timestamps(): void
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
    }

    public function softDeletes(string $column = 'deleted_at'): ColumnBlueprint
    {
        return $this->datetime($column)->nullable();
    }

    public function raw(string $definition): void
    {
        $this->columns[] = ColumnBlueprint::raw($definition);
    }

    public function modify(string $name, ColumnType|string $type, int|string|null $length = null): ColumnBlueprint
    {
        $column = new ColumnBlueprint($name, $type, $length, $this);
        $this->modifiedColumns[] = $column;

        return $column;
    }

    public function renameColumn(string $from, string $to): ColumnBlueprint
    {
        $column = $this->modify($from, '')->renameTo($to);

        return $column;
    }

    public function dropColumn(string $column): void
    {
        $column = trim($column);

        if ($column === '') {
            throw new \InvalidArgumentException('Column name cannot be empty.');
        }

        $this->droppedColumns[] = $column;
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public function primary(string|array $columns, ?string $name = null, bool $ifNotExists = false): void
    {
        $this->indexes[] = IndexDefinition::primary($columns, $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public function index(string|array $columns, ?string $name = null, bool $ifNotExists = false): void
    {
        $this->indexes[] = IndexDefinition::index($columns, $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public function unique(string|array $columns, ?string $name = null, bool $ifNotExists = false): void
    {
        $this->indexes[] = IndexDefinition::unique($columns, $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public function fulltext(string|array $columns, ?string $name = null, bool $ifNotExists = false): void
    {
        $this->indexes[] = IndexDefinition::fulltext($columns, $name, $ifNotExists);
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public function spatial(string|array $columns, ?string $name = null, bool $ifNotExists = false): void
    {
        $this->indexes[] = IndexDefinition::spatial($columns, $name, $ifNotExists);
    }

    public function dropIndex(string $name): void
    {
        $this->droppedIndexes[] = $name;
    }

    public function dropPrimary(?string $name = null): void
    {
        $this->droppedIndexes[] = $name ?? 'PRIMARY';
    }

    /**
     * @param string|non-empty-list<string> $columns
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKeyBlueprint
    {
        $foreignKey = new ForeignKeyBlueprint($this->normalizeColumns($columns));

        if ($name !== null) {
            $foreignKey->name($name);
        }

        $this->foreignKeys[] = $foreignKey;

        return $foreignKey;
    }

    public function dropForeign(string $name): void
    {
        $this->droppedForeignKeys[] = $name;
    }

    public function options(TableOptions $options): void
    {
        $this->options = $options;
    }

    public function engine(string $engine): void
    {
        $this->options = ($this->options ?? TableOptions::make())->engine($engine);
    }

    public function charset(string $charset): void
    {
        $this->options = ($this->options ?? TableOptions::make())->charset($charset);
    }

    public function collation(string $collation): void
    {
        $this->options = ($this->options ?? TableOptions::make())->collation($collation);
    }

    public function comment(string $comment): void
    {
        $this->options = ($this->options ?? TableOptions::make())->comment($comment);
    }

    public function toDefinition(): TableDefinition
    {
        $definition = TableDefinition::create($this->name)->withOptions($this->options);

        foreach ($this->columns as $column) {
            $definition = $definition->withColumn($column->definition());
        }

        foreach ($this->modifiedColumns as $column) {
            $definition = $definition->withModifiedColumn($column->definition());
        }

        foreach ($this->droppedColumns as $column) {
            $definition = $definition->withoutColumn($column);
        }

        foreach ($this->indexes as $index) {
            $definition = $definition->withIndex($index);
        }

        foreach ($this->droppedIndexes as $index) {
            $definition = $definition->withoutIndex($index);
        }

        foreach ($this->foreignKeys as $foreignKey) {
            $definition = $definition->withForeignKey($foreignKey->definition());
        }

        foreach ($this->droppedForeignKeys as $foreignKey) {
            $definition = $definition->withoutForeignKey($foreignKey);
        }

        return $definition;
    }

    private function column(string $name, ColumnType|string $type, int|string|null $length = null): ColumnBlueprint
    {
        $column = new ColumnBlueprint($name, $type, $length, $this);
        $this->columns[] = $column;

        return $column;
    }

    /**
     * @param string|non-empty-list<string> $columns
     * @return non-empty-list<string>
     */
    private function normalizeColumns(string|array $columns): array
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
