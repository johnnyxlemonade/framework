<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Output;

use Lemonade\Framework\Debug\Dump\Contract\DumpOutputInterface;

final class EchoOutput implements DumpOutputInterface
{
    public function write(string $contents): void
    {
        echo $contents;
    }
}
