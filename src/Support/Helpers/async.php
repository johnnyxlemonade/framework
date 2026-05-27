<?php

declare(strict_types=1);

use Lemonade\Framework\Event\EventDispatcherInterface;
use Lemonade\Framework\Queue\QueueBusInterface;

if (!function_exists('event')) {
    function event(object $event): object
    {
        $dispatcher = service(EventDispatcherInterface::class);

        if (!$dispatcher instanceof EventDispatcherInterface) {
            return $event;
        }

        return $dispatcher->dispatch($event);
    }
}

if (!function_exists('queue')) {
    function queue(object $message, ?string $transport = null, string $queue = 'default', int $delaySeconds = 0): void
    {
        $bus = service(QueueBusInterface::class);

        if (!$bus instanceof QueueBusInterface) {
            return;
        }

        $bus->dispatch($message, $transport, $queue, $delaySeconds);
    }
}
