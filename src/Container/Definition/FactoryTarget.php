<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Definition;

use Closure;

final readonly class FactoryTarget implements DefinitionTarget
{
    /** @param Closure(\Lemonade\Framework\Container\ContainerInterface):mixed $factory */
    public function __construct(
        public Closure $factory,
    ) {}
}
