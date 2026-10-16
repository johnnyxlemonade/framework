<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

/**
 * Registers command metadata and resolves command names and aliases for execution.
 */
final class CommandRegistry
{
    /**
     * @var array<string, CommandDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<string, string>
     */
    private array $names = [];

    /**
     * Creates a registry that resolves command implementations through the container.
     */
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Registers a legacy command whose metadata must be read from an instance.
     *
     * New code should register a definition so listing commands remains lazy.
     *
     * @param class-string<CommandInterface>|CommandInterface $command
     *
     * @throws RuntimeException When the class cannot be resolved as a command.
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

    /**
     * Registers command metadata and all aliases as one atomic namespace entry.
     *
     * @throws RuntimeException When the primary name or an alias is already registered.
     */
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

    /**
     * Reports whether a primary command name or alias is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->names[$name]);
    }

    /**
     * Resolves a registered command implementation by primary name or alias.
     *
     * This legacy lookup resolves through the root container; normal execution should use the CLI kernel.
     *
     * @throws RuntimeException When the name is unknown or the service does not implement the command contract.
     */
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

    /**
     * Returns command metadata by primary name or alias without instantiating the command.
     *
     * @throws RuntimeException When the name is not registered.
     */
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
     * Returns all definitions sorted by their primary command name.
     *
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
     * Resolves all registered commands in primary-name order.
     *
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
