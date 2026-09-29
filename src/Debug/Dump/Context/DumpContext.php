<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Context;

final readonly class DumpContext
{
    public function __construct(
        private DumpSourceLocation $sourceLocation,
        private bool $cli,
        private string $sapi,
    ) {
    }

    public function sourceLocation(): DumpSourceLocation
    {
        return $this->sourceLocation;
    }

    public function isCli(): bool
    {
        return $this->cli;
    }

    public function sapi(): string
    {
        return $this->sapi;
    }
}
