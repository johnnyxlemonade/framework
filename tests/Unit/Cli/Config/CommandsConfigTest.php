<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cli\Config;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Cli\Config\CommandsConfig;
use PHPUnit\Framework\TestCase;

final class CommandsConfigTest extends TestCase
{
    public function testExposesCommandCollectionsThroughReadOnlyAccessors(): void
    {
        $definition = new CommandDefinition(
            name: 'configured',
            commandClass: CommandsConfigTestCommand::class,
            description: 'Configured command',
        );
        $config = new CommandsConfig(
            definitions: [$definition],
            commandClasses: [CommandsConfigTestCommand::class],
        );

        self::assertSame([$definition], $config->definitions());
        self::assertSame([CommandsConfigTestCommand::class], $config->commandClasses());
    }
}

final class CommandsConfigTestCommand implements CommandInterface
{
    public function name(): string
    {
        return 'configured';
    }

    public function description(): string
    {
        return 'Configured command';
    }

    public function run(array $args): int
    {
        return 0;
    }
}
