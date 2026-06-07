<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema;

use Closure;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;

final class SchemaCompiler
{
    public function __construct(
        private readonly SchemaGrammarInterface $grammar,
    ) {}

    /**
     * @param Closure(TableBlueprint):void $definition
     */
    public function compileCreate(
        string $table,
        Closure $definition,
        bool $ifNotExists = false,
    ): string {
        return $this->compileCreateStatements(
            table: $table,
            definition: $definition,
            ifNotExists: $ifNotExists,
        )[0];
    }

    /**
     * @param Closure(TableBlueprint):void $definition
     *
     * @return non-empty-list<string>
     */
    public function compileCreateStatements(
        string $table,
        Closure $definition,
        bool $ifNotExists = false,
    ): array {
        $blueprint = new TableBlueprint($table);

        $definition($blueprint);

        return $this->compileCreateTableStatements(
            $blueprint
                ->toDefinition()
                ->withIfNotExists($ifNotExists),
        );
    }

    public function compileCreateTable(TableDefinition $definition): string
    {
        return $this->grammar->compileCreateTable($definition);
    }

    /**
     * @return non-empty-list<string>
     */
    public function compileCreateTableStatements(TableDefinition $definition): array
    {
        return $this->grammar->compileCreateTableStatements($definition);
    }

    /**
     * @param Closure(TableBlueprint):void $definition
     *
     * @return list<string>
     */
    public function compileTable(string $table, Closure $definition): array
    {
        $blueprint = new TableBlueprint($table);

        $definition($blueprint);

        return $this->grammar->compileAlterTable(
            $blueprint->toDefinition(),
        );
    }

    public function compileDrop(string $table, bool $ifExists = false): string
    {
        return $this->grammar->compileDropTable($table, $ifExists);
    }

    public function compileRename(string $from, string $to): string
    {
        return $this->grammar->compileRenameTable($from, $to);
    }

    public function compileCreateDatabase(string $database): string
    {
        return $this->grammar->compileCreateDatabase($database);
    }

    public function compileDropDatabase(string $database): string
    {
        return $this->grammar->compileDropDatabase($database);
    }
}
