<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Session;

use Lemonade\Framework\Session\SessionIdGenerator;
use PHPUnit\Framework\TestCase;

final class SessionIdGeneratorTest extends TestCase
{
    public function testGeneratedIdentifiersHaveCanonicalFormatAndAreDistinct(): void
    {
        $generator = new SessionIdGenerator();
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $first);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $second);
        self::assertNotSame($first, $second);
    }

    public function testValidationAcceptsOnlyCanonicalIdentifiers(): void
    {
        $generator = new SessionIdGenerator();

        self::assertTrue($generator->isValid(str_repeat('a', 64)));
        self::assertTrue($generator->isValid(hash('sha256', 'session')));
        self::assertFalse($generator->isValid(str_repeat('a', 63)));
        self::assertFalse($generator->isValid(str_repeat('A', 64)));
        self::assertFalse($generator->isValid(str_repeat('g', 64)));
        self::assertFalse($generator->isValid(str_repeat('a', 64) . "\n"));
    }
}
