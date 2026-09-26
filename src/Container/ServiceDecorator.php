<?php

declare(strict_types=1);

namespace Lemonade\Framework\Container;

use Closure;

final readonly class ServiceDecorator
{
    /** @param Closure(ContainerInterface, mixed):mixed|class-string<ServiceDecoratorInterface> $decorator */
    public function __construct(
        public Closure|string $decorator,
        public int $priority,
        public int $order,
    ) {}
}
