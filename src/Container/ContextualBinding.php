<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

final readonly class ContextualBinding
{
    /** @param 'dependency'|'parameter' $kind */
    public function __construct(
        public string $consumer,
        public string $kind,
        public string $key,
        public ContextualBindingValue $value,
    ) {}
}
