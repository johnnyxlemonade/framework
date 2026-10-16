<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Core\Config\Internal;

use Lemonade\Framework\Core\Config\Internal\ConfigValueNormalizer;
use PHPUnit\Framework\TestCase;

final class ConfigValueNormalizerTest extends TestCase
{
    public function testScalarStringOrPreservesScalarTextWithoutTrimming(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        self::assertSame(' value ', ConfigValueNormalizer::scalarStringOr(' value ', 'fallback'));
        self::assertSame('', ConfigValueNormalizer::scalarStringOr(false, 'fallback'));
        self::assertSame('1', ConfigValueNormalizer::scalarStringOr(true, 'fallback'));
        self::assertSame('fallback', ConfigValueNormalizer::scalarStringOr([], 'fallback'));
        self::assertSame('fallback', ConfigValueNormalizer::scalarStringOr($stringable, 'fallback'));
    }

    public function testNonEmptyStringOrTrimsScalarsAndFallsBackForBlankOrStructuredInput(): void
    {
        self::assertSame('value', ConfigValueNormalizer::nonEmptyStringOr(' value ', 'fallback'));
        self::assertSame('42', ConfigValueNormalizer::nonEmptyStringOr(42, 'fallback'));
        self::assertSame('fallback', ConfigValueNormalizer::nonEmptyStringOr('   ', 'fallback'));
        self::assertSame('fallback', ConfigValueNormalizer::nonEmptyStringOr(false, 'fallback'));
        self::assertSame('fallback', ConfigValueNormalizer::nonEmptyStringOr([], 'fallback'));
        self::assertSame(
            'fallback',
            ConfigValueNormalizer::nonEmptyStringOr(new \stdClass(), 'fallback'),
        );
    }

    public function testNullableStringTrimsScalarsAndTreatsBlankOrStructuredInputAsAbsent(): void
    {
        self::assertSame('value', ConfigValueNormalizer::nullableString(' value '));
        self::assertSame('42', ConfigValueNormalizer::nullableString(42));
        self::assertNull(ConfigValueNormalizer::nullableString(null));
        self::assertNull(ConfigValueNormalizer::nullableString('   '));
        self::assertNull(ConfigValueNormalizer::nullableString(false));
        self::assertNull(ConfigValueNormalizer::nullableString([]));
    }

    public function testIntOrAcceptsOnlyCanonicalNumericInputAndTruncatesFractions(): void
    {
        self::assertSame(12, ConfigValueNormalizer::intOr(12, 7));
        self::assertSame(12, ConfigValueNormalizer::intOr(12.9, 7));
        self::assertSame(12, ConfigValueNormalizer::intOr('12.9', 7));
        self::assertSame(100, ConfigValueNormalizer::intOr('1e2', 7));
        self::assertSame(7, ConfigValueNormalizer::intOr(true, 7));
        self::assertSame(7, ConfigValueNormalizer::intOr('invalid', 7));
    }

    public function testNullableIntUsesTheSameNumericRulesWithoutAFallback(): void
    {
        self::assertSame(12, ConfigValueNormalizer::nullableInt(12));
        self::assertSame(12, ConfigValueNormalizer::nullableInt(12.9));
        self::assertSame(12, ConfigValueNormalizer::nullableInt('12.9'));
        self::assertNull(ConfigValueNormalizer::nullableInt(null));
        self::assertNull(ConfigValueNormalizer::nullableInt(true));
        self::assertNull(ConfigValueNormalizer::nullableInt('invalid'));
    }

    public function testFloatOrAcceptsOnlyCanonicalNumericInput(): void
    {
        self::assertSame(12.0, ConfigValueNormalizer::floatOr(12, 7.5));
        self::assertSame(12.9, ConfigValueNormalizer::floatOr(12.9, 7.5));
        self::assertSame(12.9, ConfigValueNormalizer::floatOr('12.9', 7.5));
        self::assertSame(100.0, ConfigValueNormalizer::floatOr('1e2', 7.5));
        self::assertSame(7.5, ConfigValueNormalizer::floatOr(true, 7.5));
        self::assertSame(7.5, ConfigValueNormalizer::floatOr('invalid', 7.5));
    }

    public function testBoolOrUsesValidatedScalarBooleanSyntax(): void
    {
        self::assertTrue(ConfigValueNormalizer::boolOr(true, false));
        self::assertTrue(ConfigValueNormalizer::boolOr('yes', false));
        self::assertFalse(ConfigValueNormalizer::boolOr('off', true));
        self::assertFalse(ConfigValueNormalizer::boolOr(0, true));
        self::assertTrue(ConfigValueNormalizer::boolOr(2, true));
        self::assertTrue(ConfigValueNormalizer::boolOr('invalid', true));
        self::assertFalse(ConfigValueNormalizer::boolOr([], false));
    }

    public function testAssocRetainsOnlyStringKeysWithoutChangingValuesOrOrder(): void
    {
        $object = new \stdClass();

        self::assertSame([
            'first' => 1,
            'second' => $object,
        ], ConfigValueNormalizer::assoc([
            'first' => 1,
            0 => 'discarded',
            'second' => $object,
        ]));
        self::assertSame([], ConfigValueNormalizer::assoc('invalid'));
    }

    public function testStringListTrimsDeduplicatesAndSkipsInvalidItemsInOrder(): void
    {
        self::assertSame(
            ['first', '2', '1', 'second'],
            ConfigValueNormalizer::stringList([
                ' first ',
                '',
                2,
                true,
                false,
                [],
                'first',
                ' second ',
            ]),
        );
        self::assertSame([], ConfigValueNormalizer::stringList('invalid'));
    }
}
