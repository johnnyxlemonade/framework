<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Odbc;

use Lemonade\Framework\Database\Connection\DatabaseConfig;
use Lemonade\Framework\Database\Driver\Odbc\OdbcSqlEscaper;
use Lemonade\Framework\Database\Schema\Definition\SqlExpression;
use PHPUnit\Framework\TestCase;

final class OdbcSqlEscaperTest extends TestCase
{
    public function testIdentifierEscaping(): void
    {
        $escaper = new OdbcSqlEscaper($this->config('pre_'));

        self::assertSame('"users"', $escaper->identifier('users'));
        self::assertSame('"schema"."users"', $escaper->identifier('schema.users'));
        self::assertSame('"na""me"', $escaper->identifier('na"me'));
        self::assertSame('*', $escaper->identifier('*'));
    }

    public function testTablePrefixing(): void
    {
        $escaper = new OdbcSqlEscaper($this->config('pre_'));

        self::assertSame('"pre_users"', $escaper->table('users'));
        self::assertSame('"pre_users"', $escaper->table('pre_users'));
    }

    public function testValueEscapingAndTypes(): void
    {
        $escaper = new OdbcSqlEscaper($this->config(''));

        self::assertSame('NULL', $escaper->value(null));
        self::assertSame('1', $escaper->value(true));
        self::assertSame('0', $escaper->value(false));
        self::assertSame('42', $escaper->value(42));
        self::assertSame('12.5', $escaper->value(12.5));
        self::assertSame("'O''Reilly'", $escaper->value("O'Reilly"));
        self::assertSame('CURRENT_DATE', $escaper->value(SqlExpression::raw('CURRENT_DATE')));
        self::assertSame("''", $escaper->value(['x']));
    }

    private function config(string $prefix): DatabaseConfig
    {
        return DatabaseConfig::fromArray([
            'driver' => 'odbc',
            'host' => '127.0.0.1',
            'port' => 1433,
            'database' => 'test',
            'username' => 'sa',
            'password' => '',
            'charset' => '',
            'prefix' => $prefix,
        ]);
    }
}
