<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

interface QueueBusInterface
{
    public function dispatch(object $message, ?string $transport = null, string $queue = 'default', int $delaySeconds = 0): void;

    /**
     * @param callable(object):void|class-string $handler
     */
    public function addHandler(string $messageClass, callable|string $handler): void;

    public function processNext(string $queue = 'default', ?string $transport = null): bool;
}
