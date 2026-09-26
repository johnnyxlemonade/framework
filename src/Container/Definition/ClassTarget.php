<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container\Definition;

final readonly class ClassTarget implements DefinitionTarget
{
    /** @param class-string|string $className */
    public function __construct(
        public string $className,
    ) {}
}
