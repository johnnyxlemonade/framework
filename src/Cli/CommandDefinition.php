<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use InvalidArgumentException;

/**
 * Holds validated metadata used to register and lazily resolve a CLI command.
 */
final readonly class CommandDefinition
{
    /**
     * Creates a command definition after validating its name, implementation, and aliases.
     *
     * @param class-string<CommandInterface> $commandClass Command implementation resolved for execution.
     * @param list<string> $aliases Additional names that resolve to the primary command name.
     *
     * @throws InvalidArgumentException When metadata cannot describe a valid command.
     */
    public function __construct(
        public string $name,
        public string $commandClass,
        public string $description,
        public array $aliases = [],
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('CLI command definition must define a non-empty name.');
        }

        if (!class_exists($commandClass) || !is_subclass_of($commandClass, CommandInterface::class)) {
            throw new InvalidArgumentException(sprintf(
                'CLI command definition class "%s" must implement %s.',
                $commandClass,
                CommandInterface::class,
            ));
        }

        foreach ($aliases as $alias) {
            if (!is_string($alias) || trim($alias) === '') {
                throw new InvalidArgumentException('CLI command definition aliases must be non-empty strings.');
            }
        }
    }
}
