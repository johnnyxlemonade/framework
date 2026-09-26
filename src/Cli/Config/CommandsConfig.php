<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInterface;

final class CommandsConfig
{
    /**
     * @param list<CommandDefinition> $definitions
     * @param list<class-string<CommandInterface>> $legacyCommandClasses
     */
    public function __construct(
        public readonly array $definitions,
        public readonly array $legacyCommandClasses = [],
    ) {}
}
