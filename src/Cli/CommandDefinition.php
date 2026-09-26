<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use InvalidArgumentException;

final readonly class CommandDefinition
{
    /**
     * @param class-string<CommandInterface> $commandClass
     * @param list<string> $aliases
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
