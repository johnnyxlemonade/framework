<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Transport;

use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\QueueTransportInterface;

final class SyncQueueTransport implements QueueTransportInterface
{
    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void
    {
        unset($message, $delaySeconds);
    }

    public function dequeue(string $queue): ?QueuedMessage
    {
        unset($queue);

        return null;
    }

    public function ack(QueuedMessage $message): void
    {
        unset($message);
    }

    public function fail(QueuedMessage $message, string $error): void
    {
        unset($message, $error);
    }
}
