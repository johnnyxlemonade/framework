<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

/**
 * Describes the command currently executing inside an isolated command scope.
 */
final readonly class CommandContext
{
    /**
     * Creates command-scoped metadata for one CLI invocation.
     *
     * @param list<string> $argv Complete argument vector received by the CLI kernel.
     * @param list<string> $args Arguments after the executable and command name.
     */
    public function __construct(
        public string $commandName,
        public array $argv,
        public array $args,
    ) {
    }
}
