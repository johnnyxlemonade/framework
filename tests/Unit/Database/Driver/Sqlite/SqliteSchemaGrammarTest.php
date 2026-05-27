<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Sqlite;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteSchemaGrammar;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteSqlEscaper;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;
use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SqliteSchemaGrammarTest extends TestCase
{
    public function testCompileCreateTableSupportsIfNotExistsAndBasicTypes(): void
    {
        $grammar = $this->grammar();
        $table = new TableBlueprint('users');
        $table->id();
        $table->boolean('is_active');
        $table->uuid('uuid')->nullable();
        $table->json('meta');
        $table->unique('uuid');
        $table->foreign('id')->references('id')->on('accounts')->onDelete(ForeignKeyAction::Cascade);

        $sql = $grammar->compileCreateTable($table->toDefinition()->withIfNotExists(true));

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS "users"', $sql);
        self::assertStringContainsString('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        self::assertStringContainsString('"is_active" INTEGER(1) NOT NULL', $sql);
        self::assertStringContainsString('"uuid" TEXT(36) NULL', $sql);
        self::assertStringContainsString('"meta" TEXT NOT NULL', $sql);
        self::assertStringContainsString('CONSTRAINT "unique_uuid" UNIQUE ("uuid")', $sql);
        self::assertStringContainsString(
            'CONSTRAINT "fk_id_accounts" FOREIGN KEY ("id") REFERENCES "accounts" ("id") ON DELETE CASCADE',
            $sql,
        );
    }

    public function testCompileCreateTableWithPlainIndexThrowsAndDoesNotSilentlyIgnoreIndex(): void
    {
        $grammar = $this->grammar();
        $table = new TableBlueprint('users');
        $table->string('email');
        $table->index('email');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support plain INDEX clause inside CREATE TABLE');

        $grammar->compileCreateTable($table->toDefinition());
    }

    public function testCompileCreateTableSupportsAutoIncrementColumnDefinition(): void
    {
        $grammar = $this->grammar();
        $table = \Lemonade\Framework\Database\Schema\Definition\TableDefinition::create('items')
            ->withColumn((new ColumnDefinition('id', ColumnType::Integer))->autoIncrement());

        $sql = $grammar->compileCreateTable($table);

        self::assertStringContainsString('"id" INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    }

    public function testCompileDropRenameAddColumnAndIndexOperations(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);

        self::assertSame('DROP TABLE IF EXISTS "p_users"', $grammar->compileDropTable('users', true));
        self::assertSame('ALTER TABLE "p_old" RENAME TO "p_new"', $grammar->compileRenameTable('old', 'new'));
        self::assertSame(
            'ALTER TABLE "p_users" ADD COLUMN "name" VARCHAR(100) NOT NULL',
            $grammar->compileAddColumn('users', new ColumnDefinition('name', ColumnType::String, 100)),
        );
        self::assertSame(
            'CREATE INDEX "p_index_name" ON "p_users" ("name")',
            $grammar->compileAddIndex('users', IndexDefinition::index('name')),
        );
        self::assertSame(
            'CREATE UNIQUE INDEX "p_uniq_email" ON "p_users" ("email")',
            $grammar->compileAddIndex('users', IndexDefinition::unique('email', 'uniq_email')),
        );
        self::assertSame(
            'DROP INDEX "p_idx_users_name"',
            $grammar->compileDropIndex('users', 'idx_users_name'),
        );
    }

    public function testUnsupportedDatabaseOperationsThrow(): void
    {
        $grammar = $this->grammar();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support CREATE DATABASE');
        $grammar->compileCreateDatabase('demo');
    }

    public function testUnsupportedDropDatabaseThrows(): void
    {
        $grammar = $this->grammar();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support DROP DATABASE');
        $grammar->compileDropDatabase('demo');
    }

    public function testUnsupportedModifyDropAndForeignKeyAlterOperationsThrow(): void
    {
        $grammar = $this->grammar();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support MODIFY COLUMN directly');
        $grammar->compileModifyColumn('users', new ColumnDefinition('name', ColumnType::String, 10));
    }

    public function testUnsupportedDropColumnThrows(): void
    {
        $grammar = $this->grammar();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support DROP COLUMN directly');
        $grammar->compileDropColumn('users', 'name');
    }

    public function testUnsupportedAddAndDropForeignKeyThrow(): void
    {
        $grammar = $this->grammar();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support adding foreign keys after table creation');
        $grammar->compileAddForeignKey('users', ForeignKeyDefinition::make('role_id', 'roles', 'id'));
    }

    public function testUnsupportedDropForeignKeyThrowSeparately(): void
    {
        $grammar = $this->grammar();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support dropping foreign keys directly');
        $grammar->compileDropForeignKey('users', 'fk_users_roles');
    }

    public function testUnsupportedPrimaryFulltextAndSpatialIndexesThrow(): void
    {
        $grammar = $this->grammar();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('does not support adding PRIMARY KEY');
        $grammar->compileAddIndex('users', IndexDefinition::primary('id'));
    }

    public function testUnsupportedFulltextAndSpatialIndexVariantsThrow(): void
    {
        $grammar = $this->grammar();

        try {
            $grammar->compileAddIndex('users', IndexDefinition::fulltext('body'));
            self::fail('Expected fulltext to fail.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('does not support fulltext indexes', strtolower($exception->getMessage()));
        }

        try {
            $grammar->compileAddIndex('users', IndexDefinition::spatial('geo'));
            self::fail('Expected spatial to fail.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('does not support spatial indexes', strtolower($exception->getMessage()));
        }
    }

    public function testCompileAlterTableAllowsOnlyAddColumnAndAddIndex(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);
        $table = \Lemonade\Framework\Database\Schema\Definition\TableDefinition::create('users')
            ->withColumn(new ColumnDefinition('nickname', ColumnType::String, 30))
            ->withIndex(IndexDefinition::index('nickname'));

        $sql = $grammar->compileAlterTable($table);

        self::assertSame('ALTER TABLE "p_users" ADD COLUMN "nickname" VARCHAR(30) NOT NULL', $sql[0]);
        self::assertSame('CREATE INDEX "p_index_nickname" ON "p_users" ("nickname")', $sql[1]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function grammar(array $config = []): SqliteSchemaGrammar
    {
        $dbConfig = DatabaseConfig::fromArray(array_merge([
            'driver' => 'pdo',
            'dialect' => 'sqlite',
            'dsn' => 'sqlite::memory:',
            'prefix' => '',
        ], $config));

        return new SqliteSchemaGrammar(
            new SqliteSqlEscaper($dbConfig),
            $dbConfig,
        );
    }
}
