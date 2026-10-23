<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Rule\ValidPasswordRule;
use Lemonade\Framework\Validation\ValidationRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidPasswordRuleTest extends TestCase
{
    #[DataProvider('acceptedPasswords')]
    public function testAcceptsModernLengthOnlyPasswordPolicy(string $password): void
    {
        self::assertTrue((new ValidPasswordRule())->validate($password, null, []));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedPasswords(): iterable
    {
        yield 'minimum length' => [str_repeat('a', 15)];
        yield 'maximum Unicode length' => [str_repeat('🔐', 128)];
        yield 'long passphrase' => ['correct horse battery staple'];
        yield 'unicode counted as characters' => [str_repeat('🔐', 15)];
        yield 'leading and trailing spaces are preserved' => [' ' . str_repeat('a', 13) . ' '];
        yield 'spaces only' => [str_repeat(' ', 15)];
        yield 'repeating characters' => [str_repeat('x', 32)];
        yield 'no uppercase, number, or special character' => ['all lowercase passphrase'];
    }

    #[DataProvider('rejectedValues')]
    public function testRejectsOutOfRangeMalformedAndNonStringValues(mixed $value): void
    {
        self::assertFalse((new ValidPasswordRule())->validate($value, null, []));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function rejectedValues(): iterable
    {
        yield 'empty' => [''];
        yield 'below minimum' => [str_repeat('a', 14)];
        yield 'above maximum' => [str_repeat('🔐', 129)];
        yield 'invalid UTF-8' => [str_repeat('a', 15) . "\xC3\x28"];
        yield 'integer' => [123456789012345];
        yield 'float' => [123456789012345.0];
        yield 'boolean' => [true];
        yield 'null' => [null];
        yield 'array' => [[str_repeat('a', 15)]];
        yield 'object' => [new class() implements \Stringable {
            public function __toString(): string
            {
                return str_repeat('a', 15);
            }
        }];
    }

    public function testLegacySpecialCharacterParameterNoLongerChangesThePolicy(): void
    {
        self::assertTrue((new ValidPasswordRule())->validate(str_repeat('a', 15), 'special', []));
        self::assertNull(ValidationRule::password(requireSpecialCharacter: true)->param());
    }
}
