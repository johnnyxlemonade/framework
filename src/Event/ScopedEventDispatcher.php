<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

final readonly class ScopedEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private EventListenerRegistry $registry, private EventListenerInvoker $invoker) {}
    public function dispatch(object $event): object
    {
        foreach ($this->registry->for($event) as $definition) {
            $this->invoker->invoke($definition, $event);
        } return $event;
    }
}
