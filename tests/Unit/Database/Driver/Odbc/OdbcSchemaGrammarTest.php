<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Odbc;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Odbc\OdbcIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Odbc\OdbcSchemaGrammar;
use Lemonade\Framework\Database\Driver\Odbc\OdbcSqlEscaper;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\SqlExpression;
use Lemonade\Framework\Database\Schema\Definition\TableOptions;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;
use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;
use PHPUnit\Framework\TestCase;

final class OdbcSchemaGrammarTest extends TestCase
{
    public function testCompileCreateAndDropDatabase(): void
    {
        $grammar = $this->grammar();

        self::assertSame('CREATE DATABASE "demo"', $grammar->compileCreateDatabase('demo'));
        self::assertSame('DROP DATABASE "demo"', $grammar->compileDropDatabase('demo'));
    }

    public function testCompileCreateDropRenameTable(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);
        $table = new TableBlueprint('users');
        $table->id();
        $table->string('email', 190)->default('john@example.com');
        $table->index('email');
        $table->foreign('id')->references('id')->on('accounts')->onDelete(ForeignKeyAction::Cascade);

        $sql = $grammar->compileCreateTable($table->toDefinition()->withIfNotExists(true));
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS "p_users"', $sql);
        self::assertStringContainsString('"id" BIGINT NOT NULL IDENTITY', $sql);
        self::assertStringContainsString('"email" VARCHAR(190) NOT NULL DEFAULT \'john@example.com\'', $sql);
        self::assertStringContainsString('INDEX "p_index_email" ("email")', $sql);
        self::assertStringContainsString(
            'CONSTRAINT "p_fk_id_accounts" FOREIGN KEY ("id") REFERENCES "p_accounts" ("id") ON DELETE CASCADE',
            $sql,
        );

        self::assertSame('DROP TABLE IF EXISTS "p_users"', $grammar->compileDropTable('users', true));
        self::assertSame('ALTER TABLE "p_old" RENAME TO "p_new"', $grammar->compileRenameTable('old', 'new'));
    }

    public function testCompileColumnsAndFlags(): void
    {
        $grammar = $this->grammar(['prefix' => 'x_']);

        self::assertSame(
            'ALTER TABLE "x_users" ADD "name" VARCHAR(100) NOT NULL',
            $grammar->compileAddColumn('users', new ColumnDefinition('name', ColumnType::String, 100)),
        );

        self::assertSame(
            'ALTER TABLE "x_users" ADD "uid" CHAR(36) NOT NULL',
            $grammar->compileAddColumn('users', new ColumnDefinition('uid', ColumnType::Uuid, 36)),
        );

        self::assertSame(
            'ALTER TABLE "x_users" ADD "flag" SMALLINT NULL DEFAULT 1',
            $grammar->compileAddColumn('users', new ColumnDefinition('flag', ColumnType::Boolean, null, false, true, true, true)),
        );

        self::assertSame(
            'ALTER TABLE "x_users" ADD "meta" TEXT NOT NULL',
            $grammar->compileAddColumn('users', new ColumnDefinition('meta', ColumnType::Json)),
        );

        self::assertSame(
            'ALTER TABLE "x_users" ADD "price" DECIMAL(8,2) NOT NULL DEFAULT CURRENT_TIMESTAMP',
            $grammar->compileAddColumn(
                'users',
                new ColumnDefinition('price', ColumnType::Decimal, '8,2', false, false, true, SqlExpression::raw('CURRENT_TIMESTAMP')),
            ),
        );

        self::assertSame(
            'ALTER TABLE "x_users" ALTER COLUMN "name" "full_name" VARCHAR(120) NOT NULL',
            $grammar->compileModifyColumn(
                'users',
                new ColumnDefinition('name', ColumnType::String, 120, false, false, false, null, false, null, null, false, 'full_name'),
            ),
        );

        self::assertSame(
            'ALTER TABLE "x_users" ALTER COLUMN "rank" INT NOT NULL',
            $grammar->compileModifyColumn('users', new ColumnDefinition('rank', ColumnType::Integer)),
        );

        self::assertSame(
            'ALTER TABLE "x_users" ADD RAW SQL',
            $grammar->compileAddColumn('users', ColumnDefinition::raw('RAW SQL')),
        );

        self::assertSame('ALTER TABLE "x_users" DROP COLUMN "old_col"', $grammar->compileDropColumn('users', 'old_col'));
    }

    public function testCommentAfterFirstIgnoredInOdbcColumnCompilation(): void
    {
        $grammar = $this->grammar();
        $column = (new ColumnDefinition('name', ColumnType::String, 20))
            ->comment('ignored')
            ->after('id')
            ->first();

        self::assertSame(
            'ALTER TABLE "users" ADD "name" VARCHAR(20) NOT NULL',
            $grammar->compileAddColumn('users', $column),
        );
    }

    public function testCompileIndexesAndDropsAndUnsupportedTypes(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);
        $primary = IndexDefinition::primary('id');
        $unique = IndexDefinition::unique('email');
        $index = IndexDefinition::index(['a', 'b']);

        self::assertSame(
            'ALTER TABLE "p_users" ADD CONSTRAINT "p_primary_id" PRIMARY KEY ("id")',
            $grammar->compileAddIndex('users', $primary),
        );
        self::assertSame(
            'ALTER TABLE "p_users" ADD CONSTRAINT "p_unique_email" UNIQUE ("email")',
            $grammar->compileAddIndex('users', $unique),
        );
        self::assertSame(
            'CREATE INDEX "p_index_a_b" ON "p_users" ("a", "b")',
            $grammar->compileAddIndex('users', $index),
        );

        self::assertSame(
            'DROP INDEX "p_idx_users_email" ON "p_users"',
            $grammar->compileDropIndex('users', 'idx_users_email'),
        );

        $this->expectException(\LogicException::class);
        $grammar->compileAddIndex('users', IndexDefinition::fulltext('content'));
    }

    public function testCompileSpatialIndexThrowsLogicException(): void
    {
        $grammar = $this->grammar();
        $this->expectException(\LogicException::class);
        $grammar->compileAddIndex('users', IndexDefinition::spatial('geo'));
    }

    public function testCompileForeignKeysAndDrop(): void
    {
        $grammar = $this->grammar(['prefix' => 'px_']);
        $fk = ForeignKeyDefinition::make('user_id', 'users', 'id')
            ->onUpdate(ForeignKeyAction::Cascade)
            ->onDelete(ForeignKeyAction::Restrict);

        self::assertSame(
            'ALTER TABLE "px_posts" ADD CONSTRAINT "px_fk_user_id_users" FOREIGN KEY ("user_id") REFERENCES "px_users" ("id") ON UPDATE CASCADE ON DELETE RESTRICT',
            $grammar->compileAddForeignKey('posts', $fk),
        );
        self::assertSame(
            'ALTER TABLE "px_posts" DROP CONSTRAINT "px_fk_posts_user"',
            $grammar->compileDropForeignKey('posts', 'fk_posts_user'),
        );
    }

    public function testCompileAlterTableStatementOrdering(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);
        $table = \Lemonade\Framework\Database\Schema\Definition\TableDefinition::create('users')
            ->withoutForeignKey('fk_old')
            ->withoutIndex('idx_old')
            ->withoutColumn('col_old')
            ->withColumn(new ColumnDefinition('col_new', ColumnType::String, 10))
            ->withModifiedColumn(new ColumnDefinition('col_mod', ColumnType::Integer))
            ->withIndex(IndexDefinition::index('col_new'))
            ->withForeignKey(ForeignKeyDefinition::make('account_id', 'accounts', 'id'));

        $sql = $grammar->compileAlterTable($table);

        self::assertSame('ALTER TABLE "p_users" DROP CONSTRAINT "p_fk_old"', $sql[0]);
        self::assertSame('DROP INDEX "p_idx_old" ON "p_users"', $sql[1]);
        self::assertSame('ALTER TABLE "p_users" DROP COLUMN "col_old"', $sql[2]);
        self::assertSame('ALTER TABLE "p_users" ADD "col_new" VARCHAR(10) NOT NULL', $sql[3]);
        self::assertSame('ALTER TABLE "p_users" ALTER COLUMN "col_mod" INT NOT NULL', $sql[4]);
        self::assertSame('CREATE INDEX "p_index_col_new" ON "p_users" ("col_new")', $sql[5]);
        self::assertSame(
            'ALTER TABLE "p_users" ADD CONSTRAINT "p_fk_account_id_accounts" FOREIGN KEY ("account_id") REFERENCES "p_accounts" ("id")',
            $sql[6],
        );
    }

    public function testTableOptionsUseOnlyExtraValuesAndEscapeValues(): void
    {
        $grammar = $this->grammar();
        $table = new TableBlueprint('opts');
        $table->string('name');
        $table->options(
            TableOptions::make()
                ->engine('ignored')
                ->charset('ignored')
                ->collation('ignored')
                ->comment('ignored')
                ->option('fillfactor', 70)
                ->option('quoted_option', "O'Reilly"),
        );

        $sql = $grammar->compileCreateTable($table->toDefinition());
        self::assertStringContainsString(' FILLFACTOR = 70', $sql);
        self::assertStringContainsString(" QUOTED_OPTION = 'O''Reilly'", $sql);
        self::assertStringNotContainsString('ENGINE', $sql);
        self::assertStringNotContainsString('CHARSET', $sql);
        self::assertStringNotContainsString('COLLATE', $sql);
        self::assertStringNotContainsString('COMMENT', $sql);
    }

    public function testIdentifierEscapingAndPrefixing(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);

        self::assertSame(
            'ALTER TABLE "p_u""sers" ADD "na""me" VARCHAR(20) NOT NULL DEFAULT \'O\'\'Reilly\'',
            $grammar->compileAddColumn(
                'u"sers',
                new ColumnDefinition('na"me', ColumnType::String, 20, false, false, true, "O'Reilly"),
            ),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function grammar(array $config = []): OdbcSchemaGrammar
    {
        $dbConfig = DatabaseConfig::fromArray(array_merge([
            'driver' => 'odbc',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database' => 'test',
            'username' => 'sa',
            'password' => '',
            'charset' => '',
            'collation' => '',
            'prefix' => '',
        ], $config));
        $escaper = new OdbcSqlEscaper(new OdbcIdentifierEscaper($dbConfig->prefix()));

        return new OdbcSchemaGrammar($escaper, $dbConfig);
    }
}
