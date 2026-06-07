<?php

declare(strict_types=1);

namespace Lemonade\Framework\Database\Schema;

use Closure;
use Lemonade\Framework\Database\DatabaseDriverInterface;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;

final class Schema
{
    public function __construct(
        private readonly SchemaCompiler $compiler,
        private readonly DatabaseDriverInterface $db,
    ) {}

    /**
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

    public function createTable(TableDefinition $definition): bool
    {
        return $this->executeMany($this->compiler->compileCreateTableStatements($definition));
    }

    /**
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

    public function drop(string $table, bool $ifExists = false): bool
    {
        return $this->execute(
            $this->compiler->compileDrop(
                table: $table,
                ifExists: $ifExists,
            ),
        );
    }

    public function rename(string $from, string $to): bool
    {
        return $this->execute(
            $this->compiler->compileRename(
                from: $from,
                to: $to,
            ),
        );
    }

    public function createDatabase(string $database): bool
    {
        return $this->execute(
            $this->compiler->compileCreateDatabase($database),
        );
    }

    public function dropDatabase(string $database): bool
    {
        return $this->execute(
            $this->compiler->compileDropDatabase($database),
        );
    }

    /**
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

    public function compileDrop(string $table, bool $ifExists = false): string
    {
        return $this->compiler->compileDrop(
            table: $table,
            ifExists: $ifExists,
        );
    }

    public function compileRename(string $from, string $to): string
    {
        return $this->compiler->compileRename(
            from: $from,
            to: $to,
        );
    }

    private function execute(string $sql): bool
    {
        return $this->db->query($sql) !== false;
    }

    /**
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
