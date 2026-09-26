<?php

declare(strict_types=1);

namespace Lemonade\Framework\Tests\Unit\Cli;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInterface;
use Lemonade\Framework\Cli\CommandRegistry;
use Lemonade\Framework\Container\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommandRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        RegistryLazyAlphaCommand::$instances = 0;
        RegistryLazyZuluCommand::$instances = 0;
    }

    public function testRegisterDefinitionDoesNotInstantiateCommand(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->registerDefinition($this->alphaDefinition());

        self::assertTrue($registry->has('alpha'));
        self::assertSame(0, RegistryLazyAlphaCommand::$instances);
    }

    public function testDefinitionsAreListedFromMetadataWithoutInstantiation(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->registerDefinition(new CommandDefinition('zulu', RegistryLazyZuluCommand::class, 'Zulu command'));
        $registry->registerDefinition($this->alphaDefinition());

        $definitions = $registry->allDefinitions();

        self::assertSame(['alpha', 'zulu'], array_map(static fn(CommandDefinition $definition): string => $definition->name, $definitions));
        self::assertSame(0, RegistryLazyAlphaCommand::$instances);
        self::assertSame(0, RegistryLazyZuluCommand::$instances);
    }

    public function testGetInstantiatesOnlySelectedCommand(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->registerDefinition($this->alphaDefinition());
        $registry->registerDefinition(new CommandDefinition('zulu', RegistryLazyZuluCommand::class, 'Zulu command'));

        $command = $registry->get('alpha');

        self::assertInstanceOf(RegistryLazyAlphaCommand::class, $command);
        self::assertSame(1, RegistryLazyAlphaCommand::$instances);
        self::assertSame(0, RegistryLazyZuluCommand::$instances);
    }

    public function testDefinitionCommandIsResolvedThroughContainer(): void
    {
        $container = new Container();
        $dependency = new RegistryCommandDependency('from-container');
        $container->singleton(RegistryCommandDependency::class, $dependency);
        $registry = new CommandRegistry($container);
        $registry->registerDefinition(new CommandDefinition('dependent', RegistryDependentCommand::class, 'Dependent command'));

        $command = $registry->get('dependent');

        self::assertInstanceOf(RegistryDependentCommand::class, $command);
        self::assertSame($dependency, $command->dependency);
    }

    public function testAliasResolvesTheDefinedCommand(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->registerDefinition(new CommandDefinition('alpha', RegistryLazyAlphaCommand::class, 'Alpha command', ['a']));

        self::assertTrue($registry->has('a'));
        self::assertSame('alpha', $registry->definition('a')->name);
    }

    public function testLegacyClassStringRegistrationStillWorks(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->register(RegistryLegacyCommand::class);

        self::assertTrue($registry->has('legacy'));
        self::assertInstanceOf(RegistryLegacyCommand::class, $registry->get('legacy'));
    }

    public function testGetThrowsForUnknownCommand(): void
    {
        $registry = new CommandRegistry(new Container());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CLI command "missing" is not registered.');
        $registry->get('missing');
    }

    public function testDuplicateCommandNameThrowsClearException(): void
    {
        $registry = new CommandRegistry(new Container());
        $registry->registerDefinition($this->alphaDefinition());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CLI command name or alias "alpha" is already registered.');
        $registry->registerDefinition(new CommandDefinition('alpha', RegistryLazyZuluCommand::class, 'Duplicate alpha'));
    }

    public function testDefinitionRejectsMissingCommandClassAtRegistration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        /** @var class-string<CommandInterface> $missingCommand */
        $missingCommand = str_replace('/', '\\', 'Missing/Command');
        new CommandDefinition('missing', $missingCommand, 'Missing command');
    }

    public function testDefinitionRejectsMissingCommandName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must define a non-empty name');

        new CommandDefinition('', RegistryLazyAlphaCommand::class, 'Missing name');
    }

    private function alphaDefinition(): CommandDefinition
    {
        return new CommandDefinition('alpha', RegistryLazyAlphaCommand::class, 'Alpha command');
    }
}

final class RegistryLazyAlphaCommand implements CommandInterface
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

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

final class RegistryLazyZuluCommand implements CommandInterface
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

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

final class RegistryLegacyCommand implements CommandInterface
{
    public function name(): string
    {
        return 'legacy';
    }

    public function description(): string
    {
        return 'Legacy command';
    }

    public function run(array $args): int
    {
        return 0;
    }
}

final class RegistryCommandDependency
{
    public function __construct(
        public readonly string $value,
    ) {}
}

final class RegistryDependentCommand implements CommandInterface
{
    public function __construct(
        public readonly RegistryCommandDependency $dependency,
    ) {}

    public function name(): string
    {
        return 'dependent';
    }

    public function description(): string
    {
        return 'Dependent command';
    }

    public function run(array $args): int
    {
        return 0;
    }
}
