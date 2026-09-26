<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

final readonly class ContainerConfig
{
    public function __construct(
        public bool $autowireFallbackWarning,
    ) {}
}
