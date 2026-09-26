<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

final readonly class CommandContext
{
    /**
     * @param list<string> $argv
     * @param list<string> $args
     */
    public function __construct(
        public string $commandName,
        public array $argv,
        public array $args,
    ) {}
}
