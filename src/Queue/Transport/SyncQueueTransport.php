<?php

declare(strict_types=1);

namespace Lemonade\Framework\Queue\Transport;

use Lemonade\Framework\Queue\QueuedMessage;
use Lemonade\Framework\Queue\QueueTransportInterface;

/**
 * Marks immediate in-process execution and rejects attempts to use it as persistent queue storage.
 */
final class SyncQueueTransport implements QueueTransportInterface
{
    /**
     * Rejects enqueue calls because synchronous messages must be invoked directly by QueueBus.
     */
    public function enqueue(QueuedMessage $message, int $delaySeconds = 0): void
    {
        unset($message, $delaySeconds);

        throw new \LogicException(
            'Sync queue transport cannot enqueue messages; QueueBus must execute them directly.',
        );
    }

    /**
     * Returns no stored message because this transport never persists work.
     */
    public function dequeue(string $queue): ?QueuedMessage
    {
        unset($queue);

        return null;
    }

    /**
     * Accepts no-op acknowledgement for compatibility with the transport contract.
     */
    public function ack(QueuedMessage $message): void
    {
        unset($message);
    }

    /**
     * Accepts no-op failure notification because synchronous errors propagate to the dispatcher.
     */
    public function fail(QueuedMessage $message, string $error): void
    {
        unset($message, $error);
    }
}
