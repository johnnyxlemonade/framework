<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Sqlite;

use Lemonade\Framework\Database\Driver\Sqlite\SqliteIdentifierEscaper;
use Lemonade\Framework\Database\Driver\Sqlite\SqliteSqlEscaper;
use Lemonade\Framework\Database\Schema\Definition\SqlExpression;
use PHPUnit\Framework\TestCase;

final class SqliteSqlEscaperTest extends TestCase
{
    public function testValueEscapingHandlesPrimitiveAndExpressionValues(): void
    {
        $escaper = $this->escaper();

        self::assertSame('NULL', $escaper->value(null));
        self::assertSame('1', $escaper->value(true));
        self::assertSame('0', $escaper->value(false));
        self::assertSame('42', $escaper->value(42));
        self::assertSame("'O''Reilly'", $escaper->value("O'Reilly"));
        self::assertSame('CURRENT_TIMESTAMP', $escaper->value(SqlExpression::raw('CURRENT_TIMESTAMP')));
    }

    public function testIdentifierEscapingAndTablePrefixing(): void
    {
        $escaper = $this->escaper('pre_');

        self::assertSame('*', $escaper->identifier('*'));
        self::assertSame('"users"', $escaper->identifier('users'));
        self::assertSame('"users"."id"', $escaper->identifier('users.id'));
        self::assertSame('"weird""name"', $escaper->identifier('weird"name'));
        self::assertSame('"pre_users"', $escaper->table('users'));
    }

    private function escaper(string $prefix = ''): SqliteSqlEscaper
    {
        return new SqliteSqlEscaper(new SqliteIdentifierEscaper($prefix));
    }
}
