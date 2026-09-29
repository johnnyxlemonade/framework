<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

use Lemonade\Framework\Container\ContainerInterface;

final readonly class EventListenerInvoker
{
    public function __construct(private ContainerInterface $container) {}
    public function invoke(EventListenerDefinition $definition, object $event): void
    {
        $listener = $this->container->get($definition->listenerClass);
        if (!is_object($listener) || !method_exists($listener, $definition->method)) {
            throw new \RuntimeException(sprintf('Event listener "%s::%s" is not callable.', $definition->listenerClass, $definition->method));
        }
        /** @phpstan-ignore-next-line Dynamic method is validated above. */
        call_user_func([$listener, $definition->method], $event);
    }
}
