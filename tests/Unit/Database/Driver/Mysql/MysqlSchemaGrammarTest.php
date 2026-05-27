<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Mysql;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSchemaGrammar;
use Lemonade\Framework\Database\Driver\Mysql\MysqlSqlEscaper;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\ColumnDefinition;
use Lemonade\Framework\Database\Schema\Definition\ForeignKeyDefinition;
use Lemonade\Framework\Database\Schema\Definition\IndexDefinition;
use Lemonade\Framework\Database\Schema\Definition\SqlExpression;
use Lemonade\Framework\Database\Schema\Definition\TableDefinition;
use Lemonade\Framework\Database\Schema\Definition\TableOptions;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;
use Lemonade\Framework\Database\Schema\Enum\ForeignKeyAction;
use PHPUnit\Framework\TestCase;

final class MysqlSchemaGrammarTest extends TestCase
{
    public function testCompileCreateAndDropDatabaseWithCharsetAndCollation(): void
    {
        $grammar = $this->grammar([
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        self::assertSame(
            'CREATE DATABASE `demo` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $grammar->compileCreateDatabase('demo'),
        );
        self::assertSame('DROP DATABASE `demo`', $grammar->compileDropDatabase('demo'));
    }

    public function testCompileCreateDropRenameTableAndOptions(): void
    {
        $grammar = $this->grammar([
            'prefix' => 'pre_',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
        ]);

        $table = new TableBlueprint('users');
        $table->id();
        $table->string('email', 190)->nullable(false)->default('john@example.com');
        $table->index('email');
        $table->foreign('id')->references('id')->on('accounts')->onDelete(ForeignKeyAction::Cascade);
        $table->options(
            TableOptions::make()
                ->engine('InnoDB')
                ->charset('latin1')
                ->collation('latin1_swedish_ci')
                ->comment('Users'),
        );

        $sql = $grammar->compileCreateTable($table->toDefinition()->withIfNotExists(true));

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `pre_users`', $sql);
        self::assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        self::assertStringContainsString("`email` VARCHAR(190) NOT NULL DEFAULT 'john@example.com'", $sql);
        self::assertStringContainsString('KEY `pre_index_email` (`email`)', $sql);
        self::assertStringContainsString(
            'CONSTRAINT `pre_fk_id_accounts` FOREIGN KEY (`id`) REFERENCES `pre_accounts` (`id`) ON DELETE CASCADE',
            $sql,
        );
        self::assertStringContainsString('ENGINE = InnoDB', $sql);
        self::assertStringContainsString('DEFAULT CHARACTER SET = latin1', $sql);
        self::assertStringContainsString('COLLATE = latin1_swedish_ci', $sql);
        self::assertStringContainsString("COMMENT = 'Users'", $sql);

        self::assertSame('DROP TABLE IF EXISTS `pre_users`', $grammar->compileDropTable('users', true));
        self::assertSame('RENAME TABLE `pre_old` TO `pre_new`', $grammar->compileRenameTable('old', 'new'));
    }

    public function testCompileColumnsAddModifyChangeDropAndColumnFlags(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);

        $string = new ColumnDefinition('name', ColumnType::String, 100);
        self::assertSame(
            'ALTER TABLE `p_users` ADD `name` VARCHAR(100) NOT NULL',
            $grammar->compileAddColumn('users', $string),
        );

        $bool = new ColumnDefinition('active', ColumnType::Boolean, 1, false, true, true, true);
        self::assertSame(
            'ALTER TABLE `p_users` ADD `active` TINYINT(1) NULL DEFAULT 1',
            $grammar->compileAddColumn('users', $bool),
        );

        $decimal = new ColumnDefinition(
            'price',
            ColumnType::Decimal,
            '8,2',
            true,
            false,
            true,
            SqlExpression::raw('CURRENT_TIMESTAMP'),
            false,
            'hello',
            'id',
            false,
        );
        self::assertSame(
            "ALTER TABLE `p_users` ADD `price` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'hello' AFTER `id`",
            $grammar->compileAddColumn('users', $decimal),
        );

        $rename = new ColumnDefinition('name', ColumnType::String, 120, false, false, false, null, false, null, null, false, 'full_name');
        self::assertSame(
            'ALTER TABLE `p_users` CHANGE `name` `full_name` VARCHAR(120) NOT NULL',
            $grammar->compileModifyColumn('users', $rename),
        );

        $first = new ColumnDefinition('rank', ColumnType::Integer, null, true, false, false, null, false, null, null, true);
        self::assertSame(
            'ALTER TABLE `p_users` MODIFY `rank` INT UNSIGNED NOT NULL FIRST',
            $grammar->compileModifyColumn('users', $first),
        );

        $raw = ColumnDefinition::raw('RAW COLUMN');
        self::assertSame('ALTER TABLE `p_users` ADD RAW COLUMN', $grammar->compileAddColumn('users', $raw));

        self::assertSame('ALTER TABLE `p_users` DROP `old_col`', $grammar->compileDropColumn('users', 'old_col'));
    }

    public function testCompileCreateTableColumnTypeMappings(): void
    {
        $grammar = $this->grammar();
        $table = new TableBlueprint('types');
        $table->char('c', 5);
        $table->uuid('u');
        $table->text('t');
        $table->mediumText('mt');
        $table->longText('lt');
        $table->tinyInteger('ti');
        $table->smallInteger('si');
        $table->mediumInteger('mi');
        $table->integer('i');
        $table->bigInteger('bi');
        $table->float('f');
        $table->double('d');
        $table->date('dt');
        $table->datetime('dttm');
        $table->timestamp('ts');
        $table->time('tm');
        $table->json('j');
        $table->binary('b');

        $sql = $grammar->compileCreateTable($table->toDefinition());
        self::assertStringContainsString('`c` CHAR(5) NOT NULL', $sql);
        self::assertStringContainsString('`u` CHAR(36) NOT NULL', $sql);
        self::assertStringContainsString('`t` TEXT NOT NULL', $sql);
        self::assertStringContainsString('`mt` MEDIUMTEXT NOT NULL', $sql);
        self::assertStringContainsString('`lt` LONGTEXT NOT NULL', $sql);
        self::assertStringContainsString('`ti` TINYINT NOT NULL', $sql);
        self::assertStringContainsString('`si` SMALLINT NOT NULL', $sql);
        self::assertStringContainsString('`mi` MEDIUMINT NOT NULL', $sql);
        self::assertStringContainsString('`i` INT NOT NULL', $sql);
        self::assertStringContainsString('`bi` BIGINT NOT NULL', $sql);
        self::assertStringContainsString('`f` FLOAT NOT NULL', $sql);
        self::assertStringContainsString('`d` DOUBLE NOT NULL', $sql);
        self::assertStringContainsString('`dt` DATE NOT NULL', $sql);
        self::assertStringContainsString('`dttm` DATETIME NOT NULL', $sql);
        self::assertStringContainsString('`ts` TIMESTAMP NOT NULL', $sql);
        self::assertStringContainsString('`tm` TIME NOT NULL', $sql);
        self::assertStringContainsString('`j` JSON NOT NULL', $sql);
        self::assertStringContainsString('`b` BINARY NOT NULL', $sql);
    }

    public function testCompileIndexesAndForeignKeysAndDrops(): void
    {
        $grammar = $this->grammar(['prefix' => 'x_']);

        $primary = IndexDefinition::primary('id');
        $index = IndexDefinition::index(['a', 'b']);
        $unique = IndexDefinition::unique('email', 'u_email');
        $fulltext = IndexDefinition::fulltext('content');
        $spatial = IndexDefinition::spatial('geo');

        self::assertSame(
            'ALTER TABLE `x_posts` ADD PRIMARY KEY (`id`)',
            $grammar->compileAddIndex('posts', $primary),
        );
        self::assertSame(
            'ALTER TABLE `x_posts` ADD KEY `x_index_a_b` (`a`, `b`)',
            $grammar->compileAddIndex('posts', $index),
        );
        self::assertSame(
            'ALTER TABLE `x_posts` ADD UNIQUE KEY `x_u_email` (`email`)',
            $grammar->compileAddIndex('posts', $unique),
        );
        self::assertSame(
            'ALTER TABLE `x_posts` ADD FULLTEXT KEY `x_fulltext_content` (`content`)',
            $grammar->compileAddIndex('posts', $fulltext),
        );
        self::assertSame(
            'ALTER TABLE `x_posts` ADD SPATIAL KEY `x_spatial_geo` (`geo`)',
            $grammar->compileAddIndex('posts', $spatial),
        );
        self::assertSame('ALTER TABLE `x_posts` DROP PRIMARY KEY', $grammar->compileDropIndex('posts', 'PRIMARY'));
        self::assertSame('ALTER TABLE `x_posts` DROP INDEX `x_idx_name`', $grammar->compileDropIndex('posts', 'idx_name'));

        $fk = ForeignKeyDefinition::make('user_id', 'users', 'id')
            ->onUpdate(ForeignKeyAction::Cascade)
            ->onDelete(ForeignKeyAction::Restrict);
        self::assertSame(
            'ALTER TABLE `x_posts` ADD CONSTRAINT `x_fk_user_id_users` FOREIGN KEY (`user_id`) REFERENCES `x_users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT',
            $grammar->compileAddForeignKey('posts', $fk),
        );
        self::assertSame(
            'ALTER TABLE `x_posts` DROP FOREIGN KEY `x_fk_posts_user`',
            $grammar->compileDropForeignKey('posts', 'fk_posts_user'),
        );
    }

    public function testCompileAlterTableStatementOrdering(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);
        $table = TableDefinition::create('users')
            ->withoutForeignKey('fk_old')
            ->withoutIndex('idx_old')
            ->withoutColumn('col_old')
            ->withColumn(new ColumnDefinition('col_new', ColumnType::String, 10))
            ->withModifiedColumn(new ColumnDefinition('col_mod', ColumnType::Integer))
            ->withIndex(IndexDefinition::index('col_new'))
            ->withForeignKey(ForeignKeyDefinition::make('account_id', 'accounts', 'id'));

        $sql = $grammar->compileAlterTable($table);

        self::assertSame('ALTER TABLE `p_users` DROP FOREIGN KEY `p_fk_old`', $sql[0]);
        self::assertSame('ALTER TABLE `p_users` DROP INDEX `p_idx_old`', $sql[1]);
        self::assertSame('ALTER TABLE `p_users` DROP `col_old`', $sql[2]);
        self::assertSame('ALTER TABLE `p_users` ADD `col_new` VARCHAR(10) NOT NULL', $sql[3]);
        self::assertSame('ALTER TABLE `p_users` MODIFY `col_mod` INT NOT NULL', $sql[4]);
        self::assertSame('ALTER TABLE `p_users` ADD KEY `p_index_col_new` (`col_new`)', $sql[5]);
        self::assertSame(
            'ALTER TABLE `p_users` ADD CONSTRAINT `p_fk_account_id_accounts` FOREIGN KEY (`account_id`) REFERENCES `p_accounts` (`id`)',
            $sql[6],
        );
    }

    public function testEscapingBackticksAndPrefixingAndValueEscaping(): void
    {
        $grammar = $this->grammar(['prefix' => 'p_']);
        self::assertSame(
            "ALTER TABLE `p_u``sers` ADD `na``me` VARCHAR(20) NOT NULL DEFAULT 'O\\'Reilly'",
            $grammar->compileAddColumn(
                'u`sers',
                new ColumnDefinition('na`me', ColumnType::String, 20, false, false, true, "O'Reilly"),
            ),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function grammar(array $config = []): MysqlSchemaGrammar
    {
        $dbConfig = DatabaseConfig::fromArray(array_merge([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => '',
            'prefix' => '',
        ], $config));
        $escaper = new MysqlSqlEscaper($dbConfig);

        return new MysqlSchemaGrammar($escaper, $dbConfig);
    }
}
