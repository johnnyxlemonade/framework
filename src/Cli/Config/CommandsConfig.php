<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Cli\CommandInterface;

final class CommandsConfig
{
    /**
     * @param list<class-string<CommandInterface>> $commands
     */
    public function __construct(
        public readonly array $commands,
    ) {}
}
