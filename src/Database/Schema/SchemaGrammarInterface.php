<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema;

use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;

interface SchemaGrammarInterface
{
    public function compileCreateDatabase(string $database): string;

    public function compileDropDatabase(string $database): string;

    public function compileCreateTable(TableDefinition $table): string;

    /**
     * @return list<string>
     */
    public function compileAlterTable(TableDefinition $table): array;

    public function compileDropTable(string $table, bool $ifExists = false): string;

    public function compileRenameTable(string $from, string $to): string;

    public function compileAddColumn(string $table, ColumnDefinition $column): string;

    public function compileModifyColumn(string $table, ColumnDefinition $column): string;

    public function compileDropColumn(string $table, string $column): string;

    public function compileAddIndex(string $table, IndexDefinition $index): string;

    public function compileDropIndex(string $table, string $index): string;

    public function compileAddForeignKey(string $table, ForeignKeyDefinition $foreignKey): string;

    public function compileDropForeignKey(string $table, string $foreignKey): string;
}
