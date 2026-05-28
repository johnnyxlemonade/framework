<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Debug\Dump;

use Lemonade\Framework\Debug\Dump\Contract\DumperInterface;
use Lemonade\Framework\Debug\Dump\DefaultDumperFactory;
use Lemonade\Framework\Debug\Dump\DumpOptions;
use PHPUnit\Framework\TestCase;

final class DefaultDumperFactoryTest extends TestCase
{
    public function testCreateReturnsDumperInterface(): void
    {
        $dumper = DefaultDumperFactory::create();

        self::assertInstanceOf(DumperInterface::class, $dumper);
    }

    public function testCreateAcceptsCustomOptions(): void
    {
        $dumper = DefaultDumperFactory::create(new DumpOptions(maxStringLength: 3));

        $output = $dumper->render('abcdef');

        self::assertStringContainsString('abc…', $output);
        self::assertStringContainsString('[truncated]', $output);
    }
}
