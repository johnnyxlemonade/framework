<?php

declare(strict_types=1);

namespace Lemonade\Framework\Cli;

final readonly class CommandInput
{
    /**
     * @param list<string> $argv
     * @param list<string> $args
     */
    public function __construct(
        private array $argv,
        private array $args,
    ) {
    }

    /** @return list<string> */
    public function argv(): array
    {
        return $this->argv;
    }

    /** @return list<string> */
    public function args(): array
    {
        return $this->args;
    }

    public function argument(int $index): ?string
    {
        return $this->args[$index] ?? null;
    }

    public function hasArgument(int $index): bool
    {
        return isset($this->args[$index]);
    }
}
