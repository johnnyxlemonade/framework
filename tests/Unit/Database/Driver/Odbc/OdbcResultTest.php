<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Odbc;

use Lemonade\Framework\Database\Driver\Odbc\OdbcField;
use Lemonade\Framework\Database\Driver\Odbc\OdbcResult;
use PHPUnit\Framework\TestCase;

final class OdbcResultTest extends TestCase
{
    public function testFetchAllAndRowHelpersAndFieldMetadata(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ];
        $fields = [
            new OdbcField('id', 'int', 11, true),
            new OdbcField('name', 'varchar', 255, false, 'x'),
        ];
        $result = OdbcResult::fromRows($rows, $fields);

        self::assertSame(2, $result->num_rows());
        self::assertSame(2, $result->num_fields());
        self::assertSame(['id', 'name'], $result->list_fields());
        self::assertCount(2, $result->field_data());
        self::assertSame($rows, $result->result_array());
        self::assertSame($rows[0], $result->row_array(0));
        self::assertSame($rows[1], $result->last_row('array'));
        self::assertNull($result->row_array(99));
    }

    public function testDataSeekAndEndOfResultBehaviorAndFreeResult(): void
    {
        $result = OdbcResult::fromRows(
            [
                ['id' => 1],
                ['id' => 2],
            ],
            [new OdbcField('id', 'int', 11)],
        );

        self::assertTrue($result->data_seek(1));
        self::assertSame(['id' => 2], $result->row_array(1));
        self::assertFalse($result->data_seek(5));
        self::assertFalse($result->data_seek(-1));

        $result->free_result();
        self::assertSame(0, $result->num_rows());
        self::assertSame(
            [
                ['id' => 1],
                ['id' => 2],
            ],
            $result->result_array(),
        );
    }
}
