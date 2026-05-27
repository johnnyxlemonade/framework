<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

use Lemonade\Framework\Container\ContainerInterface;
use RuntimeException;

final class CommandRegistry
{
    /**
     * @var array<string, class-string<CommandInterface>>
     */
    private array $commands = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @param class-string<CommandInterface> $commandClass
     */
    public function register(string $commandClass): void
    {
        if (!class_exists($commandClass)) {
            throw new RuntimeException(sprintf(
                'CLI command class "%s" does not exist.',
                $commandClass,
            ));
        }

        $resolved = $this->container->get($commandClass);

        $name = trim($resolved->name());

        if ($name === '') {
            throw new RuntimeException(sprintf(
                'CLI command "%s" must define a non-empty name.',
                $commandClass,
            ));
        }

        $this->commands[$name] = $commandClass;
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    public function get(string $name): CommandInterface
    {
        if (!isset($this->commands[$name])) {
            throw new RuntimeException(sprintf(
                'CLI command "%s" is not registered.',
                $name,
            ));
        }

        $command = $this->container->get($this->commands[$name]);

        return $command;
    }

    /**
     * @return list<CommandInterface>
     */
    public function all(): array
    {
        $items = [];

        foreach (array_keys($this->commands) as $name) {
            $items[] = $this->get($name);
        }

        usort(
            $items,
            static fn(CommandInterface $a, CommandInterface $b): int => strcmp($a->name(), $b->name()),
        );

        return $items;
    }
}
