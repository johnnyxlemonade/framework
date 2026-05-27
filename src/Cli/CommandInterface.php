<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

interface CommandInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @param list<string> $args
     */
    public function run(array $args): int;
}
