<?php

declare(strict_types=1);

if (!function_exists('event')) {
    function event(object $event): object
    {
        throw new LogicException('The global event() helper no longer resolves framework services. Inject EventDispatcherInterface explicitly.');
    }
}

if (!function_exists('queue')) {
    function queue(object $message, ?string $transport = null, string $queue = 'default', int $delaySeconds = 0): void
    {
        throw new LogicException('The global queue() helper no longer resolves framework services. Inject QueueBusInterface explicitly.');
    }
}
