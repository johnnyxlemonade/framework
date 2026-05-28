<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Contract;

interface DumpOutputInterface
{
    public function write(string $contents): void;
}
