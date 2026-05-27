<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cli;

use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommandRegistryTest extends TestCase
{
    public function testRegisterRegistersCommandClassAndHasReturnsTrue(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->register(RegistryAlphaCommand::class);

        self::assertTrue($registry->has('alpha'));
    }

    public function testHasReturnsFalseForUnknownCommand(): void
    {
        $registry = new CommandRegistry(new Container());

        self::assertFalse($registry->has('missing'));
    }

    public function testGetReturnsCommandInstanceByName(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->register(RegistryAlphaCommand::class);

        $command = $registry->get('alpha');

        self::assertInstanceOf(RegistryAlphaCommand::class, $command);
    }

    public function testGetThrowsForUnknownCommand(): void
    {
        $registry = new CommandRegistry(new Container());

        $this->expectException(RuntimeException::class);
        $registry->get('missing');
    }

    public function testAllReturnsAllRegisteredCommandsSortedByName(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->register(RegistryZuluCommand::class);
        $registry->register(RegistryAlphaCommand::class);

        $all = $registry->all();

        self::assertCount(2, $all);
        self::assertSame('alpha', $all[0]->name());
        self::assertSame('zulu', $all[1]->name());
    }
}

final class RegistryAlphaCommand implements CommandInterface
{
    public function name(): string
    {
        return 'alpha';
    }

    public function description(): string
    {
        return 'Alpha command';
    }

    public function run(array $args): int
    {
        return 0;
    }
}

final class RegistryZuluCommand implements CommandInterface
{
    public function name(): string
    {
        return 'zulu';
    }

    public function description(): string
    {
        return 'Zulu command';
    }

    public function run(array $args): int
    {
        return 0;
    }
}
