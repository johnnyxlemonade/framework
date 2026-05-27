<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Sql;

use Lemonade\Framework\Database\Sql\IdentifierProtector;
use Lemonade\Framework\Database\Sql\QuotedIdentifierEscaper;
use PHPUnit\Framework\TestCase;

final class IdentifierProtectorTest extends TestCase
{
    public function testMysqlStyleProtection(): void
    {
        $protector = new IdentifierProtector(new QuotedIdentifierEscaper('pre_', '`'));

        self::assertSame('`users`.`id`', $protector->protect('users.id'));
        self::assertSame('`users`.`id` AS `user_id`', $protector->protect('users.id AS user_id'));
        self::assertSame('*', $protector->protect('*'));
        self::assertSame('COUNT(*)', $protector->protect('COUNT(*)'));
        self::assertSame("'literal'", $protector->protect("'literal'"));
    }

    public function testOdbcStyleProtection(): void
    {
        $protector = new IdentifierProtector(new QuotedIdentifierEscaper('', '"'));

        self::assertSame('"users"."id"', $protector->protect('users.id'));
        self::assertSame('"users"."id" AS "user_id"', $protector->protect('users.id AS user_id'));
    }
}
