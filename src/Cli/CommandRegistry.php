<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

final class CommandRegistry
{
    /** @var array<string, CommandDefinition> */
    private array $definitions = [];

    /** @var array<string, string> */
    private array $names = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * Legacy registration for commands whose metadata is only available from
     * an instance. New code should use registerDefinition().
     *
     * @param class-string<CommandInterface>|CommandInterface $command
     */
    public function register(string|CommandInterface $command): void
    {
        if ($command instanceof CommandInterface) {
            $this->registerDefinition(new CommandDefinition(
                name: trim($command->name()),
                commandClass: $command::class,
                description: $command->description(),
            ));

            return;
        }

        if (!class_exists($command)) {
            throw new RuntimeException(sprintf(
                'CLI command class "%s" does not exist.',
                $command,
            ));
        }

        $resolved = $this->container->get($command);

        if (!$resolved instanceof CommandInterface) {
            throw new RuntimeException(sprintf(
                'CLI command "%s" must implement %s.',
                $command,
                CommandInterface::class,
            ));
        }

        $this->register($resolved);
    }

    public function registerDefinition(CommandDefinition $definition): void
    {
        $registeredNames = [];

        foreach ([$definition->name, ...$definition->aliases] as $name) {
            if (isset($this->names[$name]) || isset($registeredNames[$name])) {
                throw new RuntimeException(sprintf(
                    'CLI command name or alias "%s" is already registered.',
                    $name,
                ));
            }

            $registeredNames[$name] = true;
        }

        $this->definitions[$definition->name] = $definition;
        $this->names[$definition->name] = $definition->name;

        foreach ($definition->aliases as $alias) {
            $this->names[$alias] = $definition->name;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->names[$name]);
    }

    public function get(string $name): CommandInterface
    {
        $definition = $this->definition($name);
        $command = $this->container->get($definition->commandClass);

        if (!$command instanceof CommandInterface) {
            throw new RuntimeException(sprintf(
                'CLI command "%s" must resolve to %s.',
                $definition->commandClass,
                CommandInterface::class,
            ));
        }

        return $command;
    }

    public function definition(string $name): CommandDefinition
    {
        $primaryName = $this->names[$name] ?? null;

        if ($primaryName === null || !isset($this->definitions[$primaryName])) {
            throw new RuntimeException(sprintf(
                'CLI command "%s" is not registered.',
                $name,
            ));
        }

        return $this->definitions[$primaryName];
    }

    /**
     * @return list<CommandDefinition>
     */
    public function allDefinitions(): array
    {
        $definitions = array_values($this->definitions);

        usort(
            $definitions,
            static fn(CommandDefinition $a, CommandDefinition $b): int => strcmp($a->name, $b->name),
        );

        return $definitions;
    }

    /**
     * @return list<CommandInterface>
     */
    public function all(): array
    {
        $items = [];

        foreach ($this->allDefinitions() as $definition) {
            $items[] = $this->get($definition->name);
        }

        return $items;
    }
}
