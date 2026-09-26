<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Definition;

final readonly class InstanceTarget implements DefinitionTarget
{
    public function __construct(
        public object $instance,
    ) {}
}
