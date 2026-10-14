<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema\Blueprint;

use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableOptions;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;

/**
 * Collects declarative column, index, foreign-key, and table-option changes for one table.
 */
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

    /**
     * Initializes this TableBlueprint with the collaborators and state required by its database contract.
     */
    public function __construct(
        private readonly string $name,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function id(string $name = 'id'): ColumnBlueprint
    {
        $column = $this->bigInteger($name)
            ->unsigned()
            ->autoIncrement();

        $this->primary($name);

        return $column;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function uuid(string $name = 'uuid'): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Uuid, 36);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function char(string $name, int $length = 255): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Char, $length);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function string(string $name, int $length = 255): ColumnBlueprint
    {
        return $this->column($name, ColumnType::String, $length);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function text(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Text);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function mediumText(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::MediumText);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function longText(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::LongText);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function integer(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Integer);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function unsignedInteger(string $name): ColumnBlueprint
    {
        return $this->integer($name)->unsigned();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function tinyInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::TinyInteger);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function smallInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::SmallInteger);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function mediumInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::MediumInteger);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function bigInteger(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::BigInteger);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function unsignedBigInteger(string $name): ColumnBlueprint
    {
        return $this->bigInteger($name)->unsigned();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function boolean(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Boolean, 1);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function decimal(string $name, int $precision = 10, int $scale = 2): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Decimal, $precision . ',' . $scale);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function float(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Float);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function double(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Double);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function date(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Date);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function datetime(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::DateTime);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function timestamp(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Timestamp);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function time(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Time);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function json(string $name): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Json);
    }

    /**
     * Adds a binary column, optionally with a fixed length for hashes and opaque identifiers.
     */
    public function binary(string $name, ?int $length = null): ColumnBlueprint
    {
        return $this->column($name, ColumnType::Binary, $length);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function timestamps(): void
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function softDeletes(string $column = 'deleted_at'): ColumnBlueprint
    {
        return $this->datetime($column)->nullable();
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function raw(string $definition): void
    {
        $this->columns[] = ColumnBlueprint::raw($definition);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function modify(string $name, ColumnType|string $type, int|string|null $length = null): ColumnBlueprint
    {
        $column = new ColumnBlueprint($name, $type, $length, $this);
        $this->modifiedColumns[] = $column;

        return $column;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function renameColumn(string $from, string $to): ColumnBlueprint
    {
        $column = $this->modify($from, '')->renameTo($to);

        return $column;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function dropIndex(string $name): void
    {
        $this->droppedIndexes[] = $name;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function dropForeign(string $name): void
    {
        $this->droppedForeignKeys[] = $name;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function options(TableOptions $options): void
    {
        $this->options = $options;
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function engine(string $engine): void
    {
        $this->options = ($this->options ?? TableOptions::make())->engine($engine);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function charset(string $charset): void
    {
        $this->options = ($this->options ?? TableOptions::make())->charset($charset);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function collation(string $collation): void
    {
        $this->options = ($this->options ?? TableOptions::make())->collation($collation);
    }

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
    public function comment(string $comment): void
    {
        $this->options = ($this->options ?? TableOptions::make())->comment($comment);
    }

    /**
     * Converts the current database value into the representation required by its consumer.
     */
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

    /**
     * Provides this part of the database contract while preserving the object's current state and driver semantics.
     */
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
