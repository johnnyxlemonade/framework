<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Contract;

use Lemonade\Framework\Debug\Dump\Context\DumpContext;
use Lemonade\Framework\Debug\Dump\Model\Dump;

interface DumpRendererInterface
{
    public function supports(DumpContext $context): bool;

    public function render(Dump $dump): string;
}
