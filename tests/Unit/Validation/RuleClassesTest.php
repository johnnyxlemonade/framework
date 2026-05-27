<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Validation;

use Lemonade\Framework\Validation\Rule\AlphaDashRule;
use Lemonade\Framework\Validation\Rule\AlphaNumericDashRule;
use Lemonade\Framework\Validation\Rule\AlphaNumericRule;
use Lemonade\Framework\Validation\Rule\AlphaNumericSpacesRule;
use Lemonade\Framework\Validation\Rule\AlphaRule;
use Lemonade\Framework\Validation\Rule\DecimalRule;
use Lemonade\Framework\Validation\Rule\DiffersRule;
use Lemonade\Framework\Validation\Rule\ExactLengthRule;
use Lemonade\Framework\Validation\Rule\InListRule;
use Lemonade\Framework\Validation\Rule\IntegerRule;
use Lemonade\Framework\Validation\Rule\IssetRule;
use Lemonade\Framework\Validation\Rule\MatchesRule;
use Lemonade\Framework\Validation\Rule\MaxLengthRule;
use Lemonade\Framework\Validation\Rule\MinLengthRule;
use Lemonade\Framework\Validation\Rule\NumericRule;
use Lemonade\Framework\Validation\Rule\RegexMatchRule;
use Lemonade\Framework\Validation\Rule\RequiredIfRule;
use Lemonade\Framework\Validation\Rule\RequiredRule;
use Lemonade\Framework\Validation\Rule\RequiredWithoutRule;
use Lemonade\Framework\Validation\Rule\RequiredWithRule;
use Lemonade\Framework\Validation\Rule\ValidEmailRule;
use Lemonade\Framework\Validation\Rule\ValidEmailsRule;
use PHPUnit\Framework\TestCase;

final class RuleClassesTest extends TestCase
{
    public function testRequiredAndIssetRules(): void
    {
        $required = new RequiredRule();
        $isset = new IssetRule();

        self::assertTrue($required->validate('x', null, []));
        self::assertFalse($required->validate('', null, []));
        self::assertTrue($isset->validate('x', null, []));
        self::assertFalse($isset->validate('   ', null, []));
    }

    public function testRequiredIfRule(): void
    {
        $rule = new RequiredIfRule();

        self::assertFalse($rule->validate('', null, ['mode' => 'strict']));
        self::assertTrue($rule->validate('', 'mode,strict', ['mode' => 'other']));
        self::assertFalse($rule->validate('', 'mode,strict', ['mode' => 'strict']));
        self::assertTrue($rule->validate('value', 'mode,strict', ['mode' => 'strict']));
    }

    public function testRequiredWithRule(): void
    {
        $rule = new RequiredWithRule();

        self::assertFalse($rule->validate('', null, ['a' => 'x']));
        self::assertTrue($rule->validate('', 'a', ['a' => '']));
        self::assertFalse($rule->validate('', 'a', ['a' => 'x']));
        self::assertTrue($rule->validate('ok', 'a', ['a' => 'x']));
    }

    public function testRequiredWithoutRule(): void
    {
        $rule = new RequiredWithoutRule();

        self::assertFalse($rule->validate('', null, ['a' => 'x']));
        self::assertFalse($rule->validate('', 'a', ['a' => '']));
        self::assertTrue($rule->validate('', 'a', ['a' => 'x']));
        self::assertTrue($rule->validate('ok', 'a', ['a' => '']));
    }

    public function testEmailRules(): void
    {
        $email = new ValidEmailRule();
        $emails = new ValidEmailsRule();

        self::assertTrue($email->validate('john@example.com', null, []));
        self::assertFalse($email->validate('invalid-email', null, []));
        self::assertTrue($emails->validate('john@example.com,jane@example.com', null, []));
        self::assertFalse($emails->validate('john@example.com,invalid-email', null, []));
    }

    public function testLengthRules(): void
    {
        $min = new MinLengthRule();
        $max = new MaxLengthRule();
        $exact = new ExactLengthRule();

        self::assertTrue($min->validate('abcd', '3', []));
        self::assertFalse($min->validate('ab', '3', []));
        self::assertTrue($max->validate('ab', '3', []));
        self::assertFalse($max->validate('abcd', '3', []));
        self::assertTrue($exact->validate('abc', '3', []));
        self::assertFalse($exact->validate('ab', '3', []));
    }

    public function testNumericIntegerDecimalRules(): void
    {
        $numeric = new NumericRule();
        $integer = new IntegerRule();
        $decimal = new DecimalRule();

        self::assertTrue($numeric->validate('-10.5', null, []));
        self::assertFalse($numeric->validate('abc', null, []));
        self::assertTrue($integer->validate('+10', null, []));
        self::assertFalse($integer->validate('10.5', null, []));
        self::assertTrue($decimal->validate('10.5', null, []));
        self::assertFalse($decimal->validate('10', null, []));
    }

    public function testAlphaRules(): void
    {
        $alpha = new AlphaRule();
        $alnum = new AlphaNumericRule();
        $alnumSpaces = new AlphaNumericSpacesRule();
        $alphaDash = new AlphaDashRule();
        $alnumDash = new AlphaNumericDashRule();

        self::assertTrue($alpha->validate('Abc', null, []));
        self::assertFalse($alpha->validate('Abc1', null, []));
        self::assertTrue($alnum->validate('Abc1', null, []));
        self::assertFalse($alnum->validate('Abc-1', null, []));
        self::assertTrue($alnumSpaces->validate('Abc 1', null, []));
        self::assertFalse($alnumSpaces->validate('Abc-1', null, []));
        self::assertTrue($alphaDash->validate('abc_1-2', null, []));
        self::assertFalse($alphaDash->validate('abc 1', null, []));
        self::assertTrue($alnumDash->validate('Abc-12', null, []));
        self::assertFalse($alnumDash->validate('Abc_12', null, []));
    }

    public function testRegexMatchesDiffersAndInListRules(): void
    {
        $regex = new RegexMatchRule();
        $matches = new MatchesRule();
        $differs = new DiffersRule();
        $inList = new InListRule();

        self::assertTrue($regex->validate('abc123', '/^[a-z0-9]+$/', []));
        self::assertFalse($regex->validate('abc-123', '/^[a-z0-9]+$/', []));
        self::assertTrue($matches->validate('secret', 'password', ['password' => 'secret']));
        self::assertFalse($matches->validate('secret', 'password', ['password' => 'other']));
        self::assertTrue($differs->validate('secret', 'password', ['password' => 'other']));
        self::assertFalse($differs->validate('secret', 'password', ['password' => 'secret']));
        self::assertTrue($inList->validate('b', 'a,b,c', []));
        self::assertFalse($inList->validate('d', 'a,b,c', []));
    }
}
