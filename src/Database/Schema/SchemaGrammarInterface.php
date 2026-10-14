<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema;

use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;

/**
 * Defines SQL compilation for portable schema operations without executing the resulting statements.
 */
interface SchemaGrammarInterface
{
    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileCreateDatabase(string $database): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileDropDatabase(string $database): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileCreateTable(TableDefinition $table): string;

    /**
     * @return non-empty-list<string>
     */
    public function compileCreateTableStatements(TableDefinition $table): array;

    /**
     * @return list<string>
     */
    public function compileAlterTable(TableDefinition $table): array;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileDropTable(string $table, bool $ifExists = false): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileRenameTable(string $from, string $to): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileAddColumn(string $table, ColumnDefinition $column): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileModifyColumn(string $table, ColumnDefinition $column): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileDropColumn(string $table, string $column): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileAddIndex(string $table, IndexDefinition $index): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileDropIndex(string $table, string $index): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileAddForeignKey(string $table, ForeignKeyDefinition $foreignKey): string;

    /**
     * Compiles the requested schema operation into SQL supported by this database dialect.
     */
    public function compileDropForeignKey(string $table, string $foreignKey): string;
}
