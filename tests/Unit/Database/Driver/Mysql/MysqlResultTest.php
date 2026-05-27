<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Mysql;

use PHPUnit\Framework\TestCase;

final class MysqlResultTest extends TestCase
{
    public function testMysqlResultRequiresRealMysqliResultAndIsNotUnitTestableWithoutDatabase(): void
    {
        $this->markTestSkipped(
            'MysqlResult wraps native mysqli_result; stable fixture requires real mysqli query result.',
        );
    }
}
