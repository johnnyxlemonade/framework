<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug\Dump;

use Lemonade\Framework\Debug\Dump\DumpOptions;
use PHPUnit\Framework\TestCase;

final class DumpOptionsTest extends TestCase
{
    public function testDefaultsReturnExpectedValues(): void
    {
        $options = DumpOptions::defaults();

        self::assertSame(6, $options->maxDepth());
        self::assertSame(100, $options->maxItems());
        self::assertSame(2000, $options->maxStringLength());
        self::assertTrue($options->showPrivateProperties());
        self::assertTrue($options->showProtectedProperties());
        self::assertTrue($options->showObjectIds());
        self::assertTrue($options->includeHtmlStyles());
    }

    public function testConstructorAcceptsCustomValues(): void
    {
        $options = new DumpOptions(
            maxDepth: 2,
            maxItems: 3,
            maxStringLength: 4,
            showPrivateProperties: false,
            showProtectedProperties: false,
            showObjectIds: false,
            includeHtmlStyles: false,
        );

        self::assertSame(2, $options->maxDepth());
        self::assertSame(3, $options->maxItems());
        self::assertSame(4, $options->maxStringLength());
        self::assertFalse($options->showPrivateProperties());
        self::assertFalse($options->showProtectedProperties());
        self::assertFalse($options->showObjectIds());
        self::assertFalse($options->includeHtmlStyles());
    }
}
