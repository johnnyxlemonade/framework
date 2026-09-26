<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInterface;
use LogicException;

final class CommandsConfigResolver
{
    public function resolve(CommandsConfigDefinition ...$configDefinitions): CommandsConfig
    {
        $definitions = [];
        $legacyCommandClasses = [];

        foreach ($configDefinitions as $definition) {
            [$definitions, $legacyCommandClasses] = $this->resolveCommands($definition->toArray());
        }

        return new CommandsConfig($definitions, $legacyCommandClasses);
    }

    /**
     * @param array<mixed> $value
     * @return array{list<CommandDefinition>, list<class-string<CommandInterface>>}
     */
    private function resolveCommands(array $value): array
    {
        $definitions = [];
        $legacyCommandClasses = [];

        foreach ($value as $command) {
            if (is_string($command)) {
                $legacyCommandClasses[] = $this->commandClass($command);

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

        return [$definitions, array_values(array_unique($legacyCommandClasses))];
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

        /** @var class-string<CommandInterface> $commandClass */
        return $commandClass;
    }
}
