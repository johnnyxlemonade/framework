<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema;

use Closure;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;

/**
 * Compiles and executes schema changes through the active driver's grammar and query interface.
 */
final readonly class Schema
{
    /**
     * Creates a schema executor bound to the configured grammar and database driver.
     */
    public function __construct(
        private SchemaCompiler $compiler,
        private DatabaseDriverInterface $db,
    ) {
    }

    /**
     * Defines and executes a new table, optionally tolerating a pre-existing table.
     *
     * @param Closure(TableBlueprint):void $definition
     */
    public function create(
        string $table,
        Closure $definition,
        bool $ifNotExists = false,
    ): bool {
        return $this->executeMany(
            $this->compiler->compileCreateStatements(
                table: $table,
                definition: $definition,
                ifNotExists: $ifNotExists,
            ),
        );
    }

    /**
     * Executes a previously materialized table definition without invoking a blueprint callback.
     */
    public function createTable(TableDefinition $definition): bool
    {
        return $this->executeMany($this->compiler->compileCreateTableStatements($definition));
    }

    /**
     * Defines and executes alterations for an existing table in grammar-defined statement order.
     *
     * @param Closure(TableBlueprint):void $definition
     */
    public function table(string $table, Closure $definition): bool
    {
        return $this->executeMany(
            $this->compiler->compileTable(
                table: $table,
                definition: $definition,
            ),
        );
    }

    /**
     * Drops a table, optionally allowing an absent table without treating it as a failure.
     */
    public function drop(string $table, bool $ifExists = false): bool
    {
        return $this->execute(
            $this->compiler->compileDrop(
                table: $table,
                ifExists: $ifExists,
            ),
        );
    }

    /**
     * Renames one table through the active grammar's identifier rules.
     */
    public function rename(string $from, string $to): bool
    {
        return $this->execute(
            $this->compiler->compileRename(
                from: $from,
                to: $to,
            ),
        );
    }

    /**
     * Creates a database through the active driver's schema grammar.
     */
    public function createDatabase(string $database): bool
    {
        return $this->execute(
            $this->compiler->compileCreateDatabase($database),
        );
    }

    /**
     * Drops a database through the active driver's schema grammar.
     */
    public function dropDatabase(string $database): bool
    {
        return $this->execute(
            $this->compiler->compileDropDatabase($database),
        );
    }

    /**
     * Compiles the first create-table statement for callers that require SQL without execution.
     *
     * @param Closure(TableBlueprint):void $definition
     */
    public function compileCreate(
        string $table,
        Closure $definition,
        bool $ifNotExists = false,
    ): string {
        return $this->compiler->compileCreate(
            table: $table,
            definition: $definition,
            ifNotExists: $ifNotExists,
        );
    }

    /**
     * Compiles every statement required to create a table, including grammar-specific auxiliary statements.
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
        return $this->compiler->compileCreateStatements(
            table: $table,
            definition: $definition,
            ifNotExists: $ifNotExists,
        );
    }

    /**
     * Compiles every statement required to alter an existing table without executing them.
     *
     * @param Closure(TableBlueprint):void $definition
     *
     * @return list<string>
     */
    public function compileTable(string $table, Closure $definition): array
    {
        return $this->compiler->compileTable(
            table: $table,
            definition: $definition,
        );
    }

    /**
     * Compiles a drop-table statement using the active grammar.
     */
    public function compileDrop(string $table, bool $ifExists = false): string
    {
        return $this->compiler->compileDrop(
            table: $table,
            ifExists: $ifExists,
        );
    }

    /**
     * Compiles a table rename statement using the active grammar.
     */
    public function compileRename(string $from, string $to): string
    {
        return $this->compiler->compileRename(
            from: $from,
            to: $to,
        );
    }

    /**
     * Executes one compiled DDL statement and normalizes a driver failure to false.
     */
    private function execute(string $sql): bool
    {
        return $this->db->query($sql) !== false;
    }

    /**
     * Executes statements sequentially and stops at the first driver failure; no implicit rollback is attempted.
     *
     * @param list<string> $statements
     */
    private function executeMany(array $statements): bool
    {
        foreach ($statements as $sql) {
            if (!$this->execute($sql)) {
                return false;
            }
        }

        return true;
    }
}
