<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Support;

use InvalidArgumentException;
use Lemonade\Framework\Support\ByteSizeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ByteSizeParserTest extends TestCase
{
    #[DataProvider('validByteSizes')]
    public function testParsesRawBytesAndBinaryUnits(int|string $input, int $expected): void
    {
        self::assertSame($expected, (new ByteSizeParser())->parse($input));
    }

    /** @return iterable<string, array{int|string, int}> */
    public static function validByteSizes(): iterable
    {
        yield 'raw integer bytes' => [10_485_760, 10_485_760];
        yield 'raw digit string bytes' => ['10485760', 10_485_760];
        yield 'kilobytes' => ['500KB', 500 * 1024];
        yield 'megabytes' => ['2MB', 2 * 1024 * 1024];
        yield 'spaced megabytes' => ['10 MB', 10 * 1024 * 1024];
        yield 'gigabytes' => ['1GB', 1024 * 1024 * 1024];
        yield 'case insensitive unit' => ['2mb', 2 * 1024 * 1024];
    }

    #[DataProvider('invalidByteSizes')]
    public function testRejectsInvalidValues(mixed $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ByteSizeParser())->parse($input);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidByteSizes(): iterable
    {
        yield 'zero integer' => [0];
        yield 'negative integer' => [-1];
        yield 'zero string' => ['0'];
        yield 'negative string' => ['-1MB'];
        yield 'decimal value' => ['1.5MB'];
        yield 'abbreviated unit' => ['10M'];
        yield 'unknown value' => ['foo'];
        yield 'empty value' => [''];
        yield 'float' => [1.5];
        yield 'raw integer overflow' => ['9223372036854775808'];
        yield 'unit multiplication overflow' => ['9223372036854775807KB'];
    }
}
