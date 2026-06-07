<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Schema\Blueprint;

use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\TableOptions;
use Lemonade\Framework\Database\Schema\Enum\ColumnType;
use Lemonade\Framework\Database\Schema\Enum\IndexType;
use PHPUnit\Framework\TestCase;

final class TableBlueprintTest extends TestCase
{
    public function testConstructorRejectsEmptyTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TableBlueprint('  ');
    }

    public function testColumnFactoryMethodsCreateExpectedTypes(): void
    {
        $table = new TableBlueprint('users');
        $table->id();
        $table->uuid('uuid');
        $table->string('name');
        $table->text('bio');
        $table->integer('age');
        $table->boolean('active');
        $table->decimal('price', 8, 2);
        $table->datetime('created');
        $table->json('payload');
        $table->binary('blob');
        $table->timestamps();
        $table->softDeletes();
        $table->raw('RAW');

        $def = $table->toDefinition();
        $columns = $def->columns();
        $byName = [];
        foreach ($columns as $col) {
            $byName[$col->name()] = $col;
        }

        self::assertSame(ColumnType::BigInteger, $byName['id']->type());
        self::assertTrue($byName['id']->isUnsigned());
        self::assertTrue($byName['id']->isAutoIncrement());
        self::assertSame(ColumnType::Uuid, $byName['uuid']->type());
        self::assertSame(ColumnType::String, $byName['name']->type());
        self::assertSame(ColumnType::Text, $byName['bio']->type());
        self::assertSame(ColumnType::Integer, $byName['age']->type());
        self::assertSame(ColumnType::Boolean, $byName['active']->type());
        self::assertSame('8,2', $byName['price']->length());
        self::assertSame(ColumnType::DateTime, $byName['created']->type());
        self::assertSame(ColumnType::Json, $byName['payload']->type());
        self::assertSame(ColumnType::Binary, $byName['blob']->type());
        self::assertTrue($byName['created_at']->nullableFlag());
        self::assertTrue($byName['updated_at']->nullableFlag());
        self::assertTrue($byName['deleted_at']->nullableFlag());
        self::assertNotEmpty($columns);
        $last = $columns[count($columns) - 1];
        self::assertTrue($last->isLiteral());
    }

    public function testModifyRenameDropAndDropValidation(): void
    {
        $table = new TableBlueprint('users');
        $table->modify('name', ColumnType::String, 120)->nullable();
        $table->renameColumn('old', 'new');
        $table->dropColumn('obsolete');

        $def = $table->toDefinition();
        self::assertCount(2, $def->modifiedColumns());
        self::assertSame('new', $def->modifiedColumns()[1]->renameTarget());
        self::assertSame(['obsolete'], $def->droppedColumns());

        $this->expectException(\InvalidArgumentException::class);
        $table->dropColumn(' ');
    }

    public function testIndexesForeignKeysAndDropsAndOptions(): void
    {
        $table = new TableBlueprint('posts');
        $table->primary('id');
        $table->index(['a', 'b']);
        $table->unique('slug');
        $table->fulltext('content');
        $table->spatial('position');
        $table->dropIndex('idx_old');
        $table->dropPrimary();
        $table->foreign('user_id', 'fk_user')->references('id')->on('users');
        $table->dropForeign('fk_old');
        $table->engine('InnoDB');
        $table->charset('utf8mb4');
        $table->collation('utf8mb4_unicode_ci');
        $table->comment('table');
        $table->options(TableOptions::make()->engine('MyISAM'));

        $def = $table->toDefinition();
        self::assertCount(5, $def->indexes());
        self::assertSame(IndexType::Primary, $def->indexes()[0]->type());
        self::assertSame(['idx_old', 'PRIMARY'], $def->droppedIndexes());
        self::assertCount(1, $def->foreignKeys());
        self::assertSame(['fk_old'], $def->droppedForeignKeys());
        self::assertSame('MyISAM', $def->options()?->engineName());
    }

    public function testIndexCanBeMarkedAsIfNotExists(): void
    {
        $table = new TableBlueprint('posts');
        $table->index('slug', 'idx_posts_slug', ifNotExists: true);

        $index = $table->toDefinition()->indexes()[0];

        self::assertTrue($index->ifNotExists());
        self::assertSame('idx_posts_slug', $index->resolvedName());
    }

    public function testForeignWithEmptyColumnsThrows(): void
    {
        $table = new TableBlueprint('x');
        $this->expectException(\InvalidArgumentException::class);
        $table->foreign(['', ' ']);
    }
}
