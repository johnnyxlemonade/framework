<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

/**
 * Provides immutable access to the arguments of the current command invocation.
 */
final readonly class CommandInput
{
    /**
     * Captures both the complete argument vector and the command-specific arguments.
     *
     * @param list<string> $argv Complete argument vector received by the CLI kernel.
     * @param list<string> $args Arguments after the executable and command name.
     */
    public function __construct(
        private array $argv,
        private array $args,
    ) {
    }

    /**
     * Returns the complete argument vector received by the CLI kernel.
     *
     * @return list<string>
     */
    public function argv(): array
    {
        return $this->argv;
    }

    /**
     * Returns the command-specific arguments in their original order.
     *
     * @return list<string>
     */
    public function args(): array
    {
        return $this->args;
    }

    /**
     * Returns a zero-based command argument, or null when the index is absent.
     */
    public function argument(int $index): ?string
    {
        return $this->args[$index] ?? null;
    }

    /**
     * Reports whether a zero-based command argument exists at the given index.
     */
    public function hasArgument(int $index): bool
    {
        return isset($this->args[$index]);
    }
}
