<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInterface;
use LogicException;

/**
 * Validates raw command configuration and produces runtime command metadata.
 */
final class CommandsConfigResolver
{
    /**
     * Resolves command entries from the last supplied configuration definition.
     *
     * Later definitions replace earlier ones in accordance with module configuration semantics.
     *
     * @throws LogicException When an entry is malformed or does not identify a command implementation.
     */
    public function resolve(CommandsConfigDefinition ...$configDefinitions): CommandsConfig
    {
        $definitions = [];
        $commandClasses = [];

        foreach ($configDefinitions as $definition) {
            [$definitions, $commandClasses] = $this->resolveCommands($definition->toArray());
        }

        return new CommandsConfig($definitions, $commandClasses);
    }

    /**
     * @param array<mixed> $value
     * @return array{list<CommandDefinition>, list<class-string<CommandInterface>>}
     */
    private function resolveCommands(array $value): array
    {
        $definitions = [];
        $commandClasses = [];

        foreach ($value as $command) {
            if (is_string($command)) {
                $commandClasses[] = $this->commandClass($command);

                continue;
            }

            if (!is_array($command)) {
                throw new LogicException('Configured command must be a class-string or command definition mapping.');
            }

            $name = $command['name'] ?? null;
            $commandClass = $command['class'] ?? null;
            $description = $command['description'] ?? null;
            $aliases = $command['aliases'] ?? [];

            if (!is_string($name) || !is_string($commandClass) || !is_string($description) || !is_array($aliases)) {
                throw new LogicException('Configured command definition must contain string name, class, description, and optional aliases list.');
            }

            $normalizedAliases = [];
            foreach ($aliases as $alias) {
                if (!is_string($alias)) {
                    throw new LogicException('Configured command definition aliases must contain only strings.');
                }

                $normalizedAliases[] = $alias;
            }

            $definitions[] = new CommandDefinition(
                name: $name,
                commandClass: $this->commandClass($commandClass),
                description: $description,
                aliases: $normalizedAliases,
            );
        }

        return [$definitions, array_values(array_unique($commandClasses))];
    }

    /**
     * @return class-string<CommandInterface>
     */
    private function commandClass(string $commandClass): string
    {
        if (!class_exists($commandClass) || !is_subclass_of($commandClass, CommandInterface::class)) {
            throw new LogicException(sprintf(
                'Configured command "%s" must implement %s.',
                $commandClass,
                CommandInterface::class,
            ));
        }

        /**
         * @var class-string<CommandInterface> $commandClass
         */
        return $commandClass;
    }
}
