<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInterface;

/**
 * Carries resolved command metadata and class-based registrations.
 */
final readonly class CommandsConfig
{
    /**
     * Creates the runtime command configuration consumed during CLI bootstrap.
     *
     * @param list<CommandDefinition> $definitions Metadata registrations that remain lazy until execution.
     * @param list<class-string<CommandInterface>> $commandClasses Classes whose metadata requires resolution.
     */
    public function __construct(
        private array $definitions,
        private array $commandClasses = [],
    ) {
    }

    /**
     * Returns metadata registrations without resolving their command implementations.
     *
     * @return list<CommandDefinition>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * Returns command classes whose metadata must be resolved during bootstrap.
     *
     * @return list<class-string<CommandInterface>>
     */
    public function commandClasses(): array
    {
        return $this->commandClasses;
    }
}
