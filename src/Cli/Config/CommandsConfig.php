<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli\Config;

use Lemonade\Framework\Cli\CommandDefinition;
use Lemonade\Framework\Cli\CommandInterface;

final readonly class CommandsConfig
{
    /**
     * @param list<CommandDefinition> $definitions
     * @param list<class-string<CommandInterface>> $legacyCommandClasses
     */
    public function __construct(
        public array $definitions,
        public array $legacyCommandClasses = [],
    ) {
    }
}
