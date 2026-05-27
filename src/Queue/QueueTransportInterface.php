<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue;

interface QueueTransportInterface
{
    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void;

    public function dequeue(string $queue): ?QueuedMessage;

    public function ack(QueuedMessage $message): void;

    public function fail(QueuedMessage $message, string $error): void;
}
