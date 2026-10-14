<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema;

use Closure;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;

/**
 * Turns schema blueprints and immutable table definitions into driver-specific SQL statements.
 */
final readonly class SchemaCompiler
{
    /**
     * Creates a compiler that delegates dialect-specific SQL to the selected grammar.
     */
    public function __construct(
        private SchemaGrammarInterface $grammar,
    ) {
    }

    /**
     * Compiles the primary create-table statement for consumers that expect one SQL string.
     *
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
     * Materializes a blueprint once and returns all statements required by the grammar.
     *
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

    /**
     * Compiles the primary SQL statement for an immutable table definition.
     */
    public function compileCreateTable(TableDefinition $definition): string
    {
        return $this->grammar->compileCreateTable($definition);
    }

    /**
     * Compiles all statements for an immutable table definition, including driver-specific indexes or constraints.
     *
     * @return non-empty-list<string>
     */
    public function compileCreateTableStatements(TableDefinition $definition): array
    {
        return $this->grammar->compileCreateTableStatements($definition);
    }

    /**
     * Materializes an alteration blueprint and compiles its statements without executing them.
     *
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

    /**
     * Compiles a drop-table operation with optional existence protection.
     */
    public function compileDrop(string $table, bool $ifExists = false): string
    {
        return $this->grammar->compileDropTable($table, $ifExists);
    }

    /**
     * Compiles a table rename operation.
     */
    public function compileRename(string $from, string $to): string
    {
        return $this->grammar->compileRenameTable($from, $to);
    }

    /**
     * Compiles creation of a database namespace.
     */
    public function compileCreateDatabase(string $database): string
    {
        return $this->grammar->compileCreateDatabase($database);
    }

    /**
     * Compiles removal of a database namespace.
     */
    public function compileDropDatabase(string $database): string
    {
        return $this->grammar->compileDropDatabase($database);
    }
}
