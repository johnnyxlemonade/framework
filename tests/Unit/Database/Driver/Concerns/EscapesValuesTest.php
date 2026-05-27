<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Database\Driver\Concerns;

use Lemonade\Framework\Database\Driver\Concerns\EscapesValues;
use PHPUnit\Framework\TestCase;

final class EscapesValuesTest extends TestCase
{
    public function testEscapeAndEscapeLikeBehavior(): void
    {
        $driver = new class {
            use EscapesValues;

            public function escape_str(string $value, bool $like = false): string
            {
                if ($like) {
                    return strtoupper($value);
                }

                return str_replace("'", "''", $value);
            }
        };

        self::assertSame('NULL', $driver->escape(null));
        self::assertSame('1', $driver->escape(true));
        self::assertSame('0', $driver->escape(false));
        self::assertSame('123', $driver->escape(123));
        self::assertSame("'abc'", $driver->escape('abc'));
        self::assertSame("''", $driver->escape(['x']));
        self::assertSame('a!%b!_c!!d', $driver->escape_like_str('a%b_c!d'));
    }
}
