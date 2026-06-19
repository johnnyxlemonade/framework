<?php

declare(strict_types=1);

namespace Lemonade\Framework\Component\Config;

final class ComponentConfig
{
    /**
     * @param array<string, class-string> $components
     */
    public function __construct(
        public readonly array $components,
    ) {}
}
