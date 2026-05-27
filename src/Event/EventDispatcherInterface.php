<?php

declare(strict_types=1);

namespace Lemonade\Framework\Event;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;

    /**
     * @param callable(object):void|class-string $listener
     */
    public function addListener(string $eventClass, callable|string $listener, int $priority = 0): void;
}
