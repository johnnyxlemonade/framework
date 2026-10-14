<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Driver\Mysql;

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

/**
 * Compiles framework schema definitions to MySQL and MariaDB DDL with configured table prefixes.
 */
final readonly class MysqlSchemaGrammar implements SchemaGrammarInterface
{
    /**
     * @var list<string>
     */
    private const array UNSIGNED_TYPES = [
        'TINYINT',
        'SMALLINT',
        'MEDIUMINT',
        'INT',
        'INTEGER',
        'BIGINT',
        'REAL',
        'DOUBLE',
        'DOUBLE PRECISION',
        'FLOAT',
        'DECIMAL',
        'NUMERIC',
    ];

    /**
     * Creates a grammar with the configured table prefix and SQL value escaper.
     */
    public function __construct(
        private MysqlSqlEscaper $escaper,
        private DatabaseConfig $config,
    ) {
    }

    /**
     * Compiles MySQL database creation with a validated database identifier.
     */
    public function compileCreateDatabase(string $database): string
    {
        $sql = 'CREATE DATABASE ' . $this->identifier($database);

        if ($this->config->charset() !== '') {
            $sql .= ' CHARACTER SET ' . $this->sanitizeIdentifierPart($this->config->charset());
        }

        if ($this->config->collation() !== '') {
            $sql .= ' COLLATE ' . $this->sanitizeIdentifierPart($this->config->collation());
        }

        return $sql;
    }

    /**
     * Compiles MySQL database removal with a validated database identifier.
     */
    public function compileDropDatabase(string $database): string
    {
        return 'DROP DATABASE ' . $this->identifier($database);
    }

    /**
     * Compiles the primary CREATE TABLE statement for one immutable table definition.
     */
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
     * Compiles every statement required to create a table and its deferred constraints.
     *
     * @return non-empty-list<string>
     */
    public function compileCreateTableStatements(TableDefinition $table): array
    {
        return [$this->compileCreateTable($table)];
    }

    /**
     * Compiles ordered MySQL ALTER TABLE statements for all operations in a blueprint definition.
     *
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

    /**
     * Compiles a DROP TABLE statement with optional MySQL existence protection.
     */
    public function compileDropTable(string $table, bool $ifExists = false): string
    {
        return sprintf(
            'DROP TABLE %s%s',
            $ifExists ? 'IF EXISTS ' : '',
            $this->table($table),
        );
    }

    /**
     * Compiles a MySQL table rename while applying configured prefixes to both names.
     */
    public function compileRenameTable(string $from, string $to): string
    {
        return sprintf(
            'RENAME TABLE %s TO %s',
            $this->table($from),
            $this->table($to),
        );
    }

    /**
     * Compiles a MySQL ALTER TABLE clause that adds a column.
     */
    public function compileAddColumn(string $table, ColumnDefinition $column): string
    {
        return sprintf(
            'ALTER TABLE %s ADD %s',
            $this->table($table),
            $this->compileColumn($column),
        );
    }

    /**
     * Compiles a MySQL ALTER TABLE clause that modifies or renames a column.
     */
    public function compileModifyColumn(string $table, ColumnDefinition $column): string
    {
        $operation = $column->renameTarget() !== null ? 'CHANGE' : 'MODIFY';

        return sprintf(
            'ALTER TABLE %s %s %s',
            $this->table($table),
            $operation,
            $this->compileColumn($column),
        );
    }

    /**
     * Compiles a MySQL ALTER TABLE clause that drops a column.
     */
    public function compileDropColumn(string $table, string $column): string
    {
        return sprintf(
            'ALTER TABLE %s DROP %s',
            $this->table($table),
            $this->identifier($column),
        );
    }

    /**
     * Compiles a MySQL ALTER TABLE clause that adds an index.
     */
    public function compileAddIndex(string $table, IndexDefinition $index): string
    {
        return sprintf(
            'ALTER TABLE %s ADD %s',
            $this->table($table),
            $this->compileCreateTableIndex($index),
        );
    }

    /**
     * Compiles a MySQL ALTER TABLE clause that drops an index.
     */
    public function compileDropIndex(string $table, string $index): string
    {
        if (strtolower($index) === 'primary') {
            return sprintf('ALTER TABLE %s DROP PRIMARY KEY', $this->table($table));
        }

        return sprintf(
            'ALTER TABLE %s DROP INDEX %s',
            $this->table($table),
            $this->identifier($this->applyPrefix($index)),
        );
    }

    /**
     * Compiles a MySQL ALTER TABLE clause that adds a foreign-key constraint.
     */
    public function compileAddForeignKey(string $table, ForeignKeyDefinition $foreignKey): string
    {
        return sprintf(
            'ALTER TABLE %s ADD %s',
            $this->table($table),
            $this->compileForeignKeyConstraint($foreignKey),
        );
    }

    /**
     * Compiles a MySQL ALTER TABLE clause that drops a foreign-key constraint.
     */
    public function compileDropForeignKey(string $table, string $foreignKey): string
    {
        return sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s',
            $this->table($table),
            $this->identifier($this->applyPrefix($foreignKey)),
        );
    }

    /**
     * Combines every supported column attribute into one MySQL column definition.
     */
    private function compileColumn(ColumnDefinition $column): string
    {
        if ($column->isLiteral()) {
            return (string) $column->literal();
        }

        $type = $this->compileColumnType($column);

        return $this->identifier($column->name())
            . $this->compileRenameTarget($column)
            . ' '
            . $type
            . $this->compileLength($column)
            . $this->compileUnsigned($column, $type)
            . $this->compileNullability($column)
            . $this->compileDefault($column)
            . $this->compileAutoIncrement($column)
            . $this->compileComment($column)
            . $this->compilePosition($column);
    }

    /**
     * Maps a framework column type and optional length to MySQL type syntax.
     */
    private function compileColumnType(ColumnDefinition $column): string
    {
        return match ($column->type()) {
            ColumnType::Boolean => 'TINYINT',
            ColumnType::Uuid => 'CHAR',
            default => strtoupper($column->typeValue()),
        };
    }

    /**
     * Compiles the target identifier appended to a MySQL column rename operation.
     */
    private function compileRenameTarget(ColumnDefinition $column): string
    {
        $target = $column->renameTarget();

        return $target !== null && $target !== ''
            ? ' ' . $this->identifier($target)
            : '';
    }

    /**
     * Formats an optional column length or precision fragment.
     */
    private function compileLength(ColumnDefinition $column): string
    {
        $length = $column->length();

        return $length !== null && $length !== ''
            ? sprintf('(%s)', $length)
            : '';
    }

    /**
     * Adds UNSIGNED only for compatible numeric MySQL column types.
     */
    private function compileUnsigned(ColumnDefinition $column, string $type): string
    {
        return $column->isUnsigned() && in_array($type, self::UNSIGNED_TYPES, true)
            ? ' UNSIGNED'
            : '';
    }

    /**
     * Compiles the explicit NULL or NOT NULL constraint for a column.
     */
    private function compileNullability(ColumnDefinition $column): string
    {
        return $column->nullableFlag() ? ' NULL' : ' NOT NULL';
    }

    /**
     * Compiles a declared default value while preserving the distinction between absent and NULL defaults.
     */
    private function compileDefault(ColumnDefinition $column): string
    {
        if (!$column->hasDefault()) {
            return '';
        }

        return ' DEFAULT ' . $this->compileValue($column->defaultValue());
    }

    /**
     * Adds AUTO_INCREMENT when requested by the column definition.
     */
    private function compileAutoIncrement(ColumnDefinition $column): string
    {
        return $column->isAutoIncrement() ? ' AUTO_INCREMENT' : '';
    }

    /**
     * Compiles an optional escaped MySQL column comment.
     */
    private function compileComment(ColumnDefinition $column): string
    {
        $comment = $column->commentText();

        return $comment !== null && $comment !== ''
            ? ' COMMENT ' . $this->compileValue($comment)
            : '';
    }

    /**
     * Compiles FIRST or AFTER placement for a column alteration.
     */
    private function compilePosition(ColumnDefinition $column): string
    {
        $afterColumn = $column->afterColumn();
        if ($afterColumn !== null) {
            return ' AFTER ' . $this->identifier($afterColumn);
        }

        return $column->firstPosition() ? ' FIRST' : '';
    }

    /**
     * Compiles an index clause embedded in a CREATE TABLE statement.
     */
    private function compileCreateTableIndex(IndexDefinition $index): string
    {
        return match ($index->type()) {
            IndexType::Primary => sprintf(
                'PRIMARY KEY (%s)',
                $this->columnList($index->columns()),
            ),
            IndexType::Unique => sprintf(
                'UNIQUE KEY %s (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Index => sprintf(
                'KEY %s (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Fulltext => sprintf(
                'FULLTEXT KEY %s (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
            IndexType::Spatial => sprintf(
                'SPATIAL KEY %s (%s)',
                $this->identifier($this->indexName($index)),
                $this->columnList($index->columns()),
            ),
        };
    }

    /**
     * Compiles a complete MySQL foreign-key constraint including reference actions.
     */
    private function compileForeignKeyConstraint(ForeignKeyDefinition $foreignKey): string
    {
        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->identifier($this->foreignKeyName($foreignKey)),
            $this->columnList($foreignKey->columns()),
            $this->table($foreignKey->referencedTable()),
            $this->columnList($foreignKey->referencedColumns()),
        );

        $updateAction = $foreignKey->updateAction();
        if ($updateAction !== null) {
            $sql .= ' ON UPDATE ' . $this->foreignKeyAction($updateAction);
        }

        $deleteAction = $foreignKey->deleteAction();
        if ($deleteAction !== null) {
            $sql .= ' ON DELETE ' . $this->foreignKeyAction($deleteAction);
        }

        return $sql;
    }

    /**
     * Converts a framework foreign-key action enum to its MySQL keyword sequence.
     */
    private function foreignKeyAction(ForeignKeyAction $action): string
    {
        return $action->value;
    }

    /**
     * Compiles supported CREATE TABLE options after validating option names.
     */
    private function compileTableOptions(?TableOptions $options): string
    {
        $sql = '';

        $engine = $options?->engineName();
        if ($engine !== null && $engine !== '') {
            $sql .= ' ENGINE = ' . $this->sanitizeIdentifierPart($engine);
        }

        $charset = $options?->charsetName() ?? $this->config->charset();
        if ($charset !== '') {
            $sql .= ' DEFAULT CHARACTER SET = ' . $this->sanitizeIdentifierPart($charset);
        }

        $collation = $options?->collationName() ?? $this->config->collation();
        if ($collation !== '') {
            $sql .= ' COLLATE = ' . $this->sanitizeIdentifierPart($collation);
        }

        $comment = $options?->commentText();
        if ($comment !== null && $comment !== '') {
            $sql .= ' COMMENT = ' . $this->compileValue($comment);
        }

        if ($options !== null) {
            foreach ($options->extra() as $name => $value) {
                $sql .= sprintf(
                    ' %s = %s',
                    strtoupper($this->sanitizeOptionName($name)),
                    $this->compileValue($value),
                );
            }
        }

        return $sql;
    }

    /**
     * Converts a schema default value to a MySQL literal through the configured escaper.
     */
    private function compileValue(mixed $value): string
    {
        return $this->escaper->value($value);
    }

    /**
     * Escapes and joins a non-empty list of identifiers for index or foreign-key clauses.
     *
     * @param non-empty-list<string> $columns
     */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map(
            fn(string $column): string => $this->identifier($column),
            $columns,
        ));
    }

    /**
     * Resolves the explicit or derived name used for an index definition.
     */
    private function indexName(IndexDefinition $index): string
    {
        return $this->applyPrefix($index->resolvedName());
    }

    /**
     * Resolves the explicit or derived name used for a foreign-key definition.
     */
    private function foreignKeyName(ForeignKeyDefinition $foreignKey): string
    {
        return $this->applyPrefix($foreignKey->resolvedName());
    }

    /**
     * Applies the configured table prefix exactly once to a table or derived constraint name.
     */
    private function applyPrefix(string $name): string
    {
        $prefix = $this->config->prefix();

        if ($prefix === '' || str_starts_with($name, $prefix)) {
            return $name;
        }

        return $prefix . $name;
    }

    /**
     * Escapes one MySQL identifier through the configured identifier escaper.
     */
    private function identifier(string $identifier): string
    {
        return $this->escaper->identifier($identifier);
    }

    /**
     * Applies the configured prefix and escapes a table identifier.
     */
    private function table(string $table): string
    {
        return $this->escaper->table($table);
    }

    /**
     * Rejects unsafe identifier fragments before they enter generated DDL.
     */
    private function sanitizeIdentifierPart(string $value): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $value);

        return is_string($sanitized) ? $sanitized : '';
    }

    /**
     * Restricts table-option names to the identifier grammar accepted by generated DDL.
     */
    private function sanitizeOptionName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

        return $name !== null && $name !== '' ? $name : 'OPTION';
    }
}
