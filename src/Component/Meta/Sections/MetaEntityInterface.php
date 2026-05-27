<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Meta\Sections;

interface MetaEntityInterface
{
    public function render(): string;
}
