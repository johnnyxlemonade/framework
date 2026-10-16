<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

/**
 * Defines command metadata and the executable entry point used by the CLI runtime.
 */
interface CommandInterface
{
    /**
     * Returns the primary name used to invoke the command.
     */
    public function name(): string;

    /**
     * Returns the human-readable summary displayed in command listings.
     */
    public function description(): string;

    /**
     * Executes the command and returns the exit code propagated by the CLI kernel.
     *
     * @param list<string> $args Arguments after the executable and command name.
     */
    public function run(array $args): int;
}
