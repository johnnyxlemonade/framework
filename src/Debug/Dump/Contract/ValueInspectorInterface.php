<?php

declare(strict_types=1);

namespace Lemonade\Framework\Debug\Dump\Contract;

use Lemonade\Framework\Debug\Dump\DumpOptions;
use Lemonade\Framework\Debug\Dump\Model\DumpNode;

interface ValueInspectorInterface
{
    public function inspect(mixed $value, DumpOptions $options): DumpNode;
}
