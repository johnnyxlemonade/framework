<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

/**
 * Builds the raw command configuration accepted by the configuration pipeline.
 */
final class CommandsConfigDefinition extends AbstractConfigDefinition
{
    /**
     * Creates an empty command configuration definition.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Returns the module key used to select the commands configuration payload.
     */
    public static function moduleKey(): string
    {
        return 'commands';
    }

    /**
     * Appends one legacy command class entry to the current definition.
     */
    public function command(string $command): self
    {
        $this->data[] = $command;

        return $this;
    }

    /**
     * Replaces the current entries with command classes or metadata mappings.
     *
     * @param list<string|array<mixed>> $commands
     */
    public function commands(array $commands): self
    {
        $this->data = array_values($commands);

        return $this;
    }
}
