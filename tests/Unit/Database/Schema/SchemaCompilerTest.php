<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Schema;

use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;
use Lemonade\Framework\Database\Schema\SchemaCompiler;
use Lemonade\Framework\Database\Schema\SchemaGrammarInterface;
use PHPUnit\Framework\TestCase;

final class SchemaCompilerTest extends TestCase
{
    public function testCompileCreateBuildsBlueprintCallsClosureDelegatesToGrammarAndSetsIfNotExists(): void
    {
        $grammar = new RecordingSchemaGrammar();
        $compiler = new SchemaCompiler($grammar);
        $closureCalled = false;

        $sql = $compiler->compileCreate('users', function ($table) use (&$closureCalled): void {
            $closureCalled = true;
            $table->string('name');
        }, true);

        self::assertTrue($closureCalled);
        self::assertSame('SQL_CREATE_users_1', $sql);
        self::assertInstanceOf(TableDefinition::class, $grammar->lastCreateTable);
        self::assertTrue($grammar->lastCreateTable->ifNotExists());
        self::assertCount(1, $grammar->lastCreateTable->columns());
    }

    public function testCompileCreateTableDelegatesToGrammar(): void
    {
        $grammar = new RecordingSchemaGrammar();
        $compiler = new SchemaCompiler($grammar);
        $def = TableDefinition::create('users');

        self::assertSame('SQL_CREATE_users_0', $compiler->compileCreateTable($def));
    }

    public function testCompileCreateStatementsReturnsGrammarStatementList(): void
    {
        $grammar = new RecordingSchemaGrammar();
        $compiler = new SchemaCompiler($grammar);

        $sql = $compiler->compileCreateStatements('users', function ($table): void {
            $table->string('name');
        }, true);

        self::assertSame(['SQL_CREATE_users_1'], $sql);
    }

    public function testCompileTableBuildsBlueprintAndDelegatesToAlter(): void
    {
        $grammar = new RecordingSchemaGrammar();
        $compiler = new SchemaCompiler($grammar);

        $sql = $compiler->compileTable('users', function ($table): void {
            $table->integer('age');
        });

        self::assertSame(['SQL_ALTER_users_1'], $sql);
        self::assertInstanceOf(TableDefinition::class, $grammar->lastAlterTable);
        self::assertCount(1, $grammar->lastAlterTable->columns());
    }

    public function testDropRenameCreateDatabaseDropDatabaseDelegateToGrammar(): void
    {
        $grammar = new RecordingSchemaGrammar();
        $compiler = new SchemaCompiler($grammar);

        self::assertSame('SQL_DROP_users_1', $compiler->compileDrop('users', true));
        self::assertSame('SQL_RENAME_old_new', $compiler->compileRename('old', 'new'));
        self::assertSame('SQL_CREATE_DB_demo', $compiler->compileCreateDatabase('demo'));
        self::assertSame('SQL_DROP_DB_demo', $compiler->compileDropDatabase('demo'));
    }
}

final class RecordingSchemaGrammar implements SchemaGrammarInterface
{
    public ?TableDefinition $lastCreateTable = null;
    public ?TableDefinition $lastAlterTable = null;
    /** @var list<string>|null */
    public ?array $alterStatements = null;

    public function compileCreateDatabase(string $database): string
    {
        return 'SQL_CREATE_DB_' . $database;
    }

    public function compileDropDatabase(string $database): string
    {
        return 'SQL_DROP_DB_' . $database;
    }

    public function compileCreateTable(TableDefinition $table): string
    {
        $this->lastCreateTable = $table;

        return sprintf('SQL_CREATE_%s_%d', $table->name(), $table->ifNotExists() ? 1 : 0);
    }

    public function compileCreateTableStatements(TableDefinition $table): array
    {
        return [$this->compileCreateTable($table)];
    }

    public function compileAlterTable(TableDefinition $table): array
    {
        $this->lastAlterTable = $table;

        if (is_array($this->alterStatements)) {
            return $this->alterStatements;
        }

        return [sprintf('SQL_ALTER_%s_%d', $table->name(), count($table->columns()))];
    }

    public function compileDropTable(string $table, bool $ifExists = false): string
    {
        return sprintf('SQL_DROP_%s_%d', $table, $ifExists ? 1 : 0);
    }

    public function compileRenameTable(string $from, string $to): string
    {
        return sprintf('SQL_RENAME_%s_%s', $from, $to);
    }

    public function compileAddColumn(string $table, ColumnDefinition $column): string
    {
        unset($table, $column);

        return 'SQL_ADD_COLUMN';
    }

    public function compileModifyColumn(string $table, ColumnDefinition $column): string
    {
        unset($table, $column);

        return 'SQL_MOD_COLUMN';
    }

    public function compileDropColumn(string $table, string $column): string
    {
        unset($table, $column);

        return 'SQL_DROP_COLUMN';
    }

    public function compileAddIndex(string $table, IndexDefinition $index): string
    {
        unset($table, $index);

        return 'SQL_ADD_INDEX';
    }

    public function compileDropIndex(string $table, string $index): string
    {
        unset($table, $index);

        return 'SQL_DROP_INDEX';
    }

    public function compileAddForeignKey(string $table, ForeignKeyDefinition $foreignKey): string
    {
        unset($table, $foreignKey);

        return 'SQL_ADD_FK';
    }

    public function compileDropForeignKey(string $table, string $foreignKey): string
    {
        unset($table, $foreignKey);

        return 'SQL_DROP_FK';
    }
}
