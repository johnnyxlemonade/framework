<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Odbc;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableOptions;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;
use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;
use Lemonade\Framework\Database\Schema\Enum\IndexType;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;

final class OdbcSchemaGrammar implements SchemaGrammarInterface
{
    public function __construct(
        private readonly OdbcSqlEscaper $escaper,
        private readonly DatabaseConfig $config,
    ) {}

    public function compileCreateDatabase(string $database): string
    {
        return 'CREATE DATABASE ' . $this->identifier($database);
    }

    public function compileDropDatabase(string $database): string
    {
        return 'DROP DATABASE ' . $this->identifier($database);
    }

    public function compileCreateTable(TableDefinition $table): string
    {
        $definitions = [];

        foreach ($table->columns() as $column) {
            $definitions[] = $this->compileColumn($column);
        }

        foreach ($table->indexes() as $index) {
            $definitions[] = $this->compileCreateTableIndex($index);
        }

        foreach ($table->foreignKeys() as $foreignKey) {
            $definitions[] = $this->compileForeignKeyConstraint($foreignKey);
        }

        return sprintf(
            "CREATE TABLE %s%s (\n\t%s\n)%s",
            $table->ifNotExists() ? 'IF NOT EXISTS ' : '',
            $this->table($table->name()),
            implode(",\n\t", $definitions),
            $this->compileTableOptions($table->options()),
        );
    }

    /**
     * @return non-empty-list<string>
     */
    public function compileCreateTableStatements(TableDefinition $table): array
    {
        return [$this->compileCreateTable($table)];
    }

    /**
     * @return list<string>
     */
    public function compileAlterTable(TableDefinition $table): array
    {
        $statements = [];

        foreach ($table->droppedForeignKeys() as $foreignKey) {
            $statements[] = $this->compileDropForeignKey($table->name(), $foreignKey);
        }

        foreach ($table->droppedIndexes() as $index) {
            $statements[] = $this->compileDropIndex($table->name(), $index);
        }

        foreach ($table->droppedColumns() as $column) {
            $statements[] = $this->compileDropColumn($table->name(), $column);
        }

        foreach ($table->columns() as $column) {
            $statements[] = $this->compileAddColumn($table->name(), $column);
        }

        foreach ($table->modifiedColumns() as $column) {
            $statements[] = $this->compileModifyColumn($table->name(), $column);
        }

        foreach ($table->indexes() as $index) {
            $statements[] = $this->compileAddIndex($table->name(), $index);
        }

        foreach ($table->foreignKeys() as $foreignKey) {
            $statements[] = $this->compileAddForeignKey($table->name(), $foreignKey);
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
            'ALTER TABLE %s ADD %s',
            $this->table($table),
            $this->compileColumn($column),
        );
    }

    public function compileModifyColumn(string $table, ColumnDefinition $column): string
    {
        return sprintf(
            'ALTER TABLE %s ALTER COLUMN %s',
            $this->table($table),
            $this->compileColumn($column),
        );
    }

    public function compileDropColumn(string $table, string $column): string
    {
        return sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->table($table),
            $this->identifier($column),
        );
    }

    public function compileAddIndex(string $table, IndexDefinition $index): string
    {
        return match ($index->type()) {
            IndexType::Primary => sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY (%s)',
                $this->table($table),
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Unique => sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s UNIQUE (%s)',
                $this->table($table),
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Index => sprintf(
                'CREATE INDEX %s ON %s (%s)',
                $this->identifier($this->indexName($index)),
                $this->table($table),
                $this->columnList($index->columns()),
            ),
            IndexType::Fulltext,
            IndexType::Spatial => throw new \LogicException(sprintf(
                'ODBC schema grammar does not support %s indexes generically.',
                $index->type()->value,
            )),
        };
    }

    public function compileDropIndex(string $table, string $index): string
    {
        return sprintf(
            'DROP INDEX %s ON %s',
            $this->identifier($this->applyPrefix($index)),
            $this->table($table),
        );
    }

    public function compileAddForeignKey(string $table, ForeignKeyDefinition $foreignKey): string
    {
        return sprintf(
            'ALTER TABLE %s ADD %s',
            $this->table($table),
            $this->compileForeignKeyConstraint($foreignKey),
        );
    }

    public function compileDropForeignKey(string $table, string $foreignKey): string
    {
        return sprintf(
            'ALTER TABLE %s DROP CONSTRAINT %s',
            $this->table($table),
            $this->identifier($this->applyPrefix($foreignKey)),
        );
    }

    private function compileColumn(ColumnDefinition $column): string
    {
        if ($column->isLiteral()) {
            return (string) $column->literal();
        }

        return $this->identifier($column->name())
            . $this->compileRenameTarget($column)
            . ' '
            . $this->compileColumnType($column)
            . $this->compileLength($column)
            . $this->compileNullability($column)
            . $this->compileDefault($column)
            . $this->compileAutoIncrement($column)
            . $this->compileComment($column)
            . $this->compilePosition($column);
    }

    private function compileColumnType(ColumnDefinition $column): string
    {
        return match ($column->type()) {
            ColumnType::Boolean => 'SMALLINT',
            ColumnType::Uuid => 'CHAR',
            ColumnType::Json => 'TEXT',
            default => strtoupper($column->typeValue()),
        };
    }

    private function compileRenameTarget(ColumnDefinition $column): string
    {
        $target = $column->renameTarget();

        return $target !== null && $target !== ''
            ? ' ' . $this->identifier($target)
            : '';
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

    private function compileAutoIncrement(ColumnDefinition $column): string
    {
        return $column->isAutoIncrement() ? ' IDENTITY' : '';
    }

    private function compileComment(ColumnDefinition $column): string
    {
        // Column comments are not part of generic ODBC DDL.
        unset($column);

        return '';
    }

    private function compilePosition(ColumnDefinition $column): string
    {
        // FIRST / AFTER is not generic ODBC syntax.
        unset($column);

        return '';
    }

    private function compileCreateTableIndex(IndexDefinition $index): string
    {
        return match ($index->type()) {
            IndexType::Primary => sprintf(
                'CONSTRAINT %s PRIMARY KEY (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Unique => sprintf(
                'CONSTRAINT %s UNIQUE (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Index => sprintf(
                'INDEX %s (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Fulltext,
            IndexType::Spatial => throw new \LogicException(sprintf(
                'ODBC schema grammar does not support %s indexes generically.',
                $index->type()->value,
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

    private function compileTableOptions(?TableOptions $options): string
    {
        if ($options === null) {
            return '';
        }

        $sql = '';

        foreach ($options->extra() as $name => $value) {
            $sql .= sprintf(
                ' %s = %s',
                strtoupper($this->sanitizeOptionName($name)),
                $this->compileValue($value),
            );
        }

        return $sql;
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

    private function sanitizeOptionName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

        return $name !== null && $name !== '' ? $name : 'OPTION';
    }
}
