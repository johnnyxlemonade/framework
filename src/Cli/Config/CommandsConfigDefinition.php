<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Core\Config\Definition\AbstractConfigDefinition;

final class CommandsConfigDefinition extends AbstractConfigDefinition
{
    public static function create(): self
    {
        return new self();
    }

    public static function moduleKey(): string
    {
        return 'commands';
    }

    public function command(string $command): self
    {
        $this->data[] = $command;

        return $this;
    }

    /** @param list<string> $commands */
    public function commands(array $commands): self
    {
        $this->data = array_values($commands);

        return $this;
    }
}
