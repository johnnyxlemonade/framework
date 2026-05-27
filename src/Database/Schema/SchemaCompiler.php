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
        $blueprint = new TableBlueprint($table);

        $definition($blueprint);

        return $this->compileCreateTable(
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
