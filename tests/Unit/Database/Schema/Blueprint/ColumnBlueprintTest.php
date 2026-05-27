<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Schema\Blueprint;

use Lemonade\Framework\Database\Schema\Blueprint\ColumnBlueprint;
use Lemonade\Framework\Database\Schema\Blueprint\TableBlueprint;
use Lemonade\Framework\Database\Schema\Definition\SqlExpression;
use Lemonade\Framework\Database\Schema\Enum\IndexType;
use PHPUnit\Framework\TestCase;

final class ColumnBlueprintTest extends TestCase
{
    public function testConstructorRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ColumnBlueprint('   ', 'string');
    }

    public function testRawCreatesLiteralColumnDefinition(): void
    {
        $definition = ColumnBlueprint::raw('RAW SQL')->definition();

        self::assertTrue($definition->isLiteral());
        self::assertSame('RAW SQL', $definition->literal());
    }

    public function testFluentMutatorsSetDefinitionFlagsAndReturnSelf(): void
    {
        $column = new ColumnBlueprint('price', 'decimal');
        $same = $column
            ->unsigned()
            ->nullable()
            ->default(10)
            ->autoIncrement()
            ->comment('x')
            ->after('other')
            ->first()
            ->renameTo('price_new');

        self::assertSame($column, $same);
        $def = $column->definition();
        self::assertTrue($def->isUnsigned());
        self::assertTrue($def->nullableFlag());
        self::assertTrue($def->hasDefault());
        self::assertSame(10, $def->defaultValue());
        self::assertTrue($def->isAutoIncrement());
        self::assertSame('x', $def->commentText());
        self::assertTrue($def->firstPosition());
        self::assertNull($def->afterColumn());
        self::assertSame('price_new', $def->renameTarget());
    }

    public function testDefaultExpressionCreatesSqlExpression(): void
    {
        $def = (new ColumnBlueprint('created_at', 'datetime'))
            ->defaultExpression('CURRENT_TIMESTAMP')
            ->definition();

        self::assertInstanceOf(SqlExpression::class, $def->defaultValue());
        /** @var SqlExpression $expr */
        $expr = $def->defaultValue();
        self::assertSame('CURRENT_TIMESTAMP', $expr->sql());
    }

    public function testAfterDisablesFirstAndFirstClearsAfter(): void
    {
        $column = new ColumnBlueprint('name', 'string');
        $column->first()->after('id');
        $defA = $column->definition();
        self::assertFalse($defA->firstPosition());
        self::assertSame('id', $defA->afterColumn());

        $column->first();
        $defB = $column->definition();
        self::assertTrue($defB->firstPosition());
        self::assertNull($defB->afterColumn());
    }

    public function testColumnIndexHelpersAddIndexesToParentBlueprint(): void
    {
        $table = new TableBlueprint('users');
        $column = $table->string('email');
        $column->primary()->index()->unique()->fulltext();
        $definition = $table->toDefinition();

        $types = array_map(
            static fn($index) => $index->type(),
            $definition->indexes(),
        );

        self::assertContains(IndexType::Primary, $types);
        self::assertContains(IndexType::Index, $types);
        self::assertContains(IndexType::Unique, $types);
        self::assertContains(IndexType::Fulltext, $types);
    }
}
