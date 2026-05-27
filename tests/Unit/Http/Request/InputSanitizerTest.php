<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Http\Request;

use Lemonade\Framework\Http\Request\InputSanitizer;
use PHPUnit\Framework\TestCase;

final class InputSanitizerTest extends TestCase
{
    public function testSanitizeArrayCleansKeysValuesAndNestedArrays(): void
    {
        $sanitizer = new InputSanitizer();
        $input = [
            "na\0me" => "Jo\x01hn%00%1f%7f",
            'ok_key-1.value' => 'clean',
            'bad key!*' => 'x',
            'nested' => [
                'sub%00key' => "a\x07b",
                123 => 'ignored-non-string-key',
                'deep' => [
                    "k\x00" => "x%00%1f%7f\x7Fz",
                ],
            ],
            'int' => 10,
            'bool' => true,
            'float' => 1.5,
        ];

        $out = $sanitizer->sanitizeArray($input);

        self::assertArrayHasKey('name', $out);
        self::assertArrayHasKey('ok_key-1.value', $out);
        self::assertArrayHasKey('badkey', $out);
        self::assertArrayHasKey('nested', $out);
        self::assertSame('John', $out['name']);
        self::assertSame('clean', $out['ok_key-1.value']);
        self::assertSame(10, $out['int']);
        self::assertTrue($out['bool']);
        self::assertSame(1.5, $out['float']);

        self::assertIsArray($out['nested']);
        /** @var array<string, mixed> $nested */
        $nested = $out['nested'];
        self::assertArrayHasKey('subkey', $nested);
        self::assertArrayHasKey('deep', $nested);
        self::assertSame('ab', $nested['subkey']);
        self::assertIsArray($nested['deep']);
        /** @var array<string, mixed> $deep */
        $deep = $nested['deep'];
        self::assertSame('xz', $deep['k']);
    }

    public function testEmptyInputReturnsEmptyArrayAndRepeatedInvisibleSequencesAreRemoved(): void
    {
        $sanitizer = new InputSanitizer();

        self::assertSame([], $sanitizer->sanitizeArray([]));

        $out = $sanitizer->sanitizeArray([
            'x' => '%00%00%1f%1f%7f%7fA',
        ]);

        self::assertSame('A', $out['x']);
    }
}
