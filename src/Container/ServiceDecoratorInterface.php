<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

/**
 * Explicit class decorator contract. The inner service is passed to decorate(),
 * while constructor dependencies continue to use normal container autowiring.
 */
interface ServiceDecoratorInterface
{
    public function decorate(mixed $inner): mixed;
}
