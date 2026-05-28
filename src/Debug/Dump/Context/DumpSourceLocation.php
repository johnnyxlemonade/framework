<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Context;

final class DumpSourceLocation
{
    public function __construct(
        private readonly string $file,
        private readonly ?int $line,
    ) {}

    public function file(): string
    {
        return $this->file;
    }

    public function line(): ?int
    {
        return $this->line;
    }

    public function toString(): string
    {
        if ($this->line === null) {
            return $this->file;
        }

        return $this->file . ':' . $this->line;
    }
}
