<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Sqlite;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;
use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;
use Lemonade\Framework\Database\Schema\Enum\IndexType;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;
use LogicException;

final class SqliteSchemaGrammar implements SchemaGrammarInterface
{
    public function __construct(
        private readonly SqliteSqlEscaper $escaper,
        private readonly DatabaseConfig $config,
    ) {}

    public function compileCreateDatabase(string $database): string
    {
        unset($database);

        throw new LogicException('SQLite schema grammar does not support CREATE DATABASE.');
    }

    public function compileDropDatabase(string $database): string
    {
        unset($database);

        throw new LogicException('SQLite schema grammar does not support DROP DATABASE.');
    }

    public function compileCreateTable(TableDefinition $table): string
    {
        $definitions = [];

        foreach ($table->columns() as $column) {
            $definitions[] = $this->compileColumn($column);
        }

        foreach ($table->indexes() as $index) {
            if ($this->isPrimaryIndexCoveredByAutoIncrementColumn($index, $table)) {
                continue;
            }

            $definitions[] = $this->compileCreateTableIndex($index);
        }

        foreach ($table->foreignKeys() as $foreignKey) {
            $definitions[] = $this->compileForeignKeyConstraint($foreignKey);
        }

        return sprintf(
            "CREATE TABLE %s%s (\n\t%s\n)",
            $table->ifNotExists() ? 'IF NOT EXISTS ' : '',
            $this->table($table->name()),
            implode(",\n\t", $definitions),
        );
    }

    /**
     * @return non-empty-list<string>
     */
    public function compileCreateTableStatements(TableDefinition $table): array
    {
        $createTable = \Lemonade\Framework\Database\Schema\Definition\TableDefinition::create($table->name())
            ->withIfNotExists($table->ifNotExists())
            ->withOptions($table->options());

        foreach ($table->columns() as $column) {
            $createTable = $createTable->withColumn($column);
        }

        foreach ($table->indexes() as $index) {
            if ($index->type() === IndexType::Index) {
                continue;
            }

            if ($this->isPrimaryIndexCoveredByAutoIncrementColumn($index, $table)) {
                continue;
            }

            $createTable = $createTable->withIndex($index);
        }

        foreach ($table->foreignKeys() as $foreignKey) {
            $createTable = $createTable->withForeignKey($foreignKey);
        }

        $statements = [$this->compileCreateTable($createTable)];

        foreach ($table->indexes() as $index) {
            if ($index->type() === IndexType::Index) {
                $statements[] = $this->compileAddIndex($table->name(), $index);
            }
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    public function compileAlterTable(TableDefinition $table): array
    {
        $statements = [];

        if ($table->droppedForeignKeys() !== []) {
            throw new LogicException('SQLite schema grammar does not support dropping foreign keys directly.');
        }

        if ($table->droppedIndexes() !== []) {
            throw new LogicException('SQLite schema grammar does not support dropping indexes via ALTER TABLE.');
        }

        if ($table->droppedColumns() !== []) {
            throw new LogicException('SQLite schema grammar does not support DROP COLUMN directly.');
        }

        if ($table->modifiedColumns() !== []) {
            throw new LogicException('SQLite schema grammar does not support MODIFY COLUMN directly.');
        }

        foreach ($table->columns() as $column) {
            $statements[] = $this->compileAddColumn($table->name(), $column);
        }

        foreach ($table->indexes() as $index) {
            $statements[] = $this->compileAddIndex($table->name(), $index);
        }

        if ($table->foreignKeys() !== []) {
            throw new LogicException('SQLite schema grammar does not support adding foreign keys after table creation.');
        }

        return $statements;
    }

    public function compileDropTable(string $table, bool $ifExists = false): string
    {
        return sprintf(
            'DROP TABLE %s%s',
            $ifExists ? 'IF EXISTS ' : '',
            $this->table($table),
        );
    }

    public function compileRenameTable(string $from, string $to): string
    {
        return sprintf(
            'ALTER TABLE %s RENAME TO %s',
            $this->table($from),
            $this->table($to),
        );
    }

    public function compileAddColumn(string $table, ColumnDefinition $column): string
    {
        return sprintf(
            'ALTER TABLE %s ADD COLUMN %s',
            $this->table($table),
            $this->compileColumn($column),
        );
    }

    public function compileModifyColumn(string $table, ColumnDefinition $column): string
    {
        unset($table, $column);

        throw new LogicException('SQLite schema grammar does not support MODIFY COLUMN directly.');
    }

    public function compileDropColumn(string $table, string $column): string
    {
        unset($table, $column);

        throw new LogicException('SQLite schema grammar does not support DROP COLUMN directly.');
    }

    public function compileAddIndex(string $table, IndexDefinition $index): string
    {
        return match ($index->type()) {
            IndexType::Primary => throw new LogicException(
                'SQLite schema grammar does not support adding PRIMARY KEY after table creation.',
            ),
            IndexType::Unique => sprintf(
                'CREATE UNIQUE INDEX %s%s ON %s (%s)',
                $index->ifNotExists() ? 'IF NOT EXISTS ' : '',
                $this->identifier($this->indexName($index)),
                $this->table($table),
                $this->columnList($index->columns()),
            ),
            IndexType::Index => sprintf(
                'CREATE INDEX %s%s ON %s (%s)',
                $index->ifNotExists() ? 'IF NOT EXISTS ' : '',
                $this->identifier($this->indexName($index)),
                $this->table($table),
                $this->columnList($index->columns()),
            ),
            IndexType::Fulltext,
            IndexType::Spatial => throw new LogicException(sprintf(
                'SQLite schema grammar does not support %s indexes.',
                strtolower($index->type()->value),
            )),
        };
    }

    public function compileDropIndex(string $table, string $index): string
    {
        unset($table);

        return sprintf(
            'DROP INDEX %s',
            $this->identifier($this->applyPrefix($index)),
        );
    }

    public function compileAddForeignKey(string $table, ForeignKeyDefinition $foreignKey): string
    {
        unset($table, $foreignKey);

        throw new LogicException('SQLite schema grammar does not support adding foreign keys after table creation.');
    }

    public function compileDropForeignKey(string $table, string $foreignKey): string
    {
        unset($table, $foreignKey);

        throw new LogicException('SQLite schema grammar does not support dropping foreign keys directly.');
    }

    private function compileColumn(ColumnDefinition $column): string
    {
        if ($column->isLiteral()) {
            return (string) $column->literal();
        }

        $type = $this->compileColumnType($column);
        $sql = $this->identifier($column->name()) . ' ';

        if ($column->isAutoIncrement()) {
            return $sql . 'INTEGER PRIMARY KEY AUTOINCREMENT';
        }

        $sql .= $type
            . $this->compileLength($column)
            . $this->compileNullability($column)
            . $this->compileDefault($column);

        return $sql;
    }

    private function compileColumnType(ColumnDefinition $column): string
    {
        return match ($column->type()) {
            ColumnType::Boolean => 'INTEGER',
            ColumnType::Uuid,
            ColumnType::Json,
            ColumnType::Text,
            ColumnType::MediumText,
            ColumnType::LongText => 'TEXT',
            ColumnType::TinyInteger,
            ColumnType::SmallInteger,
            ColumnType::MediumInteger,
            ColumnType::Integer,
            ColumnType::BigInteger => 'INTEGER',
            default => strtoupper($column->typeValue()),
        };
    }

    private function isPrimaryIndexCoveredByAutoIncrementColumn(IndexDefinition $index, TableDefinition $table): bool
    {
        if ($index->type() !== IndexType::Primary || count($index->columns()) !== 1) {
            return false;
        }

        $columnName = $index->columns()[0];

        foreach ($table->columns() as $column) {
            if ($column->name() === $columnName && $column->isAutoIncrement()) {
                return true;
            }
        }

        return false;
    }

    private function compileLength(ColumnDefinition $column): string
    {
        $length = $column->length();

        return $length !== null && $length !== ''
            ? sprintf('(%s)', $length)
            : '';
    }

    private function compileNullability(ColumnDefinition $column): string
    {
        return $column->nullableFlag() ? ' NULL' : ' NOT NULL';
    }

    private function compileDefault(ColumnDefinition $column): string
    {
        if (!$column->hasDefault()) {
            return '';
        }

        return ' DEFAULT ' . $this->compileValue($column->defaultValue());
    }

    private function compileCreateTableIndex(IndexDefinition $index): string
    {
        return match ($index->type()) {
            IndexType::Primary => sprintf(
                'PRIMARY KEY (%s)',
                $this->columnList($index->columns()),
            ),
            IndexType::Unique => sprintf(
                'CONSTRAINT %s UNIQUE (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Index => throw new LogicException(
                'SQLite schema grammar does not support plain INDEX clause inside CREATE TABLE.',
            ),
            IndexType::Fulltext,
            IndexType::Spatial => throw new LogicException(sprintf(
                'SQLite schema grammar does not support %s indexes.',
                strtolower($index->type()->value),
            )),
        };
    }

    private function compileForeignKeyConstraint(ForeignKeyDefinition $foreignKey): string
    {
        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->identifier($this->foreignKeyName($foreignKey)),
            $this->columnList($foreignKey->columns()),
            $this->table($foreignKey->referencedTable()),
            $this->columnList($foreignKey->referencedColumns()),
        );

        if ($foreignKey->updateAction() !== null) {
            $sql .= ' ON UPDATE ' . $this->foreignKeyAction($foreignKey->updateAction());
        }

        if ($foreignKey->deleteAction() !== null) {
            $sql .= ' ON DELETE ' . $this->foreignKeyAction($foreignKey->deleteAction());
        }

        return $sql;
    }

    private function foreignKeyAction(ForeignKeyAction $action): string
    {
        return $action->value;
    }

    private function compileValue(mixed $value): string
    {
        return $this->escaper->value($value);
    }

    /**
     * @param non-empty-list<string> $columns
     */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map(
            fn(string $column): string => $this->identifier($column),
            $columns,
        ));
    }

    private function indexName(IndexDefinition $index): string
    {
        return $this->applyPrefix($index->resolvedName());
    }

    private function foreignKeyName(ForeignKeyDefinition $foreignKey): string
    {
        return $this->applyPrefix($foreignKey->resolvedName());
    }

    private function applyPrefix(string $name): string
    {
        $prefix = $this->config->prefix();

        if ($prefix === '' || str_starts_with($name, $prefix)) {
            return $name;
        }

        return $prefix . $name;
    }

    private function identifier(string $identifier): string
    {
        return $this->escaper->identifier($identifier);
    }

    private function table(string $table): string
    {
        return $this->escaper->table($table);
    }
}
