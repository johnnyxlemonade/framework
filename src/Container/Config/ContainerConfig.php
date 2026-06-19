<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Config;

final class ContainerConfig
{
    public function __construct(
        public readonly bool $autowireFallbackWarning,
    ) {}
}
