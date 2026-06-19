<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Cli\CommandInterface;
use LogicException;

final class CommandsConfigResolver
{
    public function resolve(CommandsConfigDefinition ...$definitions): CommandsConfig
    {
        $commands = [];

        foreach ($definitions as $definition) {
            $commands = $this->resolveCommands($definition->toArray());
        }

        return new CommandsConfig($commands);
    }

    /**
     * @param array<mixed> $value
     * @return list<class-string<CommandInterface>>
     */
    private function resolveCommands(array $value): array
    {
        $commands = [];

        foreach ($value as $commandClass) {
            if (!is_string($commandClass)) {
                throw new LogicException('Configured command must be a class-string.');
            }

            if (!class_exists($commandClass) || !is_subclass_of($commandClass, CommandInterface::class)) {
                throw new LogicException(sprintf(
                    'Configured command "%s" must implement %s.',
                    $commandClass,
                    CommandInterface::class,
                ));
            }

            /** @var class-string<CommandInterface> $commandClass */
            $commands[] = $commandClass;
        }

        return array_values(array_unique($commands));
    }
}
